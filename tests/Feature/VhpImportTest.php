<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\Property;
use App\Models\ReportWeek;
use Database\Seeders\PropertySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class VhpImportTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-vhp-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PropertySeeder::class);
        config(['services.vhp.import_secret' => self::SECRET, 'services.vhp.timestamp_tolerance' => 300]);
    }

    /** A minimal SM-format workbook (one Section B block) written to disk. */
    private function fixturePath(): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $sm1 = $book->createSheet();
        $sm1->setTitle('SM.1');
        $sm1->setCellValue('B4', 'Month');
        $sm1->setCellValue('B5', 'January');
        $sm1->fromArray([50, 0.90, 0.92, 0.80, 2000000, 2100000, 1900000, 100000000, 110000000, 95000000], null, 'C5');

        $path = tempnam(sys_get_temp_dir(), 'vhp_').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    /**
     * Build a signed multipart request payload.
     *
     * @return array{headers: array<string,string>, file: UploadedFile, data: array<string,string>}
     */
    private function signedRequest(string $property, ?int $timestamp = null, ?string $secret = null, ?string $startDate = '2026-09-25'): array
    {
        $path = $this->fixturePath();
        $contents = file_get_contents($path);
        $timestamp ??= now()->getTimestamp();
        $secret ??= self::SECRET;

        $canonical = $timestamp."\n".$property."\n".hash('sha256', $contents);
        $signature = hash_hmac('sha256', $canonical, $secret);

        $file = new UploadedFile($path, 'BKDS_Weekly_Report_SM_25_Sep_-_1_Oct_2026.xlsx', null, null, true);

        $data = ['property' => $property];
        if ($startDate !== null) {
            $data['start_date'] = $startDate;
        }

        return [
            'headers' => ['X-Vhp-Timestamp' => (string) $timestamp, 'X-Vhp-Signature' => $signature],
            'file' => $file,
            'data' => $data,
        ];
    }

    private function sendImport(array $req)
    {
        return $this->call('POST', route('api.vhp-import'), $req['data'], [], ['report' => $req['file']], $this->transformHeadersToServerVars($req['headers']));
    }

    public function test_valid_signed_request_applies_data(): void
    {
        $res = $this->sendImport($this->signedRequest('BKDS'));

        $res->assertOk()->assertJson(['status' => 'applied', 'property' => 'BKDS']);

        $property = Property::where('code', 'BKDS')->firstOrFail();
        $this->assertDatabaseHas('report_weeks', ['property_id' => $property->id, 'start_date' => '2026-09-25 00:00:00']);
        $this->assertDatabaseHas('imports', ['source' => 'vhp', 'status' => 'applied', 'property_id' => $property->id]);
        $this->assertSame(12, ReportWeek::where('property_id', $property->id)->first()->monthlyStats()->count());
    }

    public function test_missing_signature_is_rejected(): void
    {
        $req = $this->signedRequest('BKDS');
        unset($req['headers']['X-Vhp-Signature']);

        $this->sendImport($req)->assertStatus(401);
        $this->assertDatabaseCount('imports', 0);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $req = $this->signedRequest('BKDS');
        $req['headers']['X-Vhp-Signature'] = str_repeat('0', 64);

        $this->sendImport($req)->assertStatus(401);
        $this->assertDatabaseCount('imports', 0);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $this->sendImport($this->signedRequest('BKDS', secret: 'wrong-secret'))->assertStatus(401);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $this->sendImport($this->signedRequest('BKDS', timestamp: now()->getTimestamp() - 1000))->assertStatus(401);
    }

    public function test_unknown_property_is_rejected(): void
    {
        $this->sendImport($this->signedRequest('ZZZ'))->assertStatus(422);
    }

    public function test_locked_week_is_not_overwritten(): void
    {
        $property = Property::where('code', 'BKDS')->firstOrFail();
        ReportWeek::create([
            'property_id' => $property->id,
            'start_date' => '2026-09-25', 'end_date' => '2026-10-01',
            'year' => 2026, 'week_number' => 39, 'label' => 'locked wk',
            'status' => ReportStatus::Approved, // a locked status
        ]);

        $this->sendImport($this->signedRequest('BKDS'))->assertStatus(409);
    }

    public function test_endpoint_is_disabled_without_a_secret(): void
    {
        config(['services.vhp.import_secret' => null]);

        $this->sendImport($this->signedRequest('BKDS'))->assertStatus(503);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\DataImport;
use App\Models\Property;
use App\Models\ReportWeek;
use App\Models\User;
use App\Services\WeeklyReportImporter;
use App\Services\WeeklyReportParser;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PropertySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DataImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PropertySeeder::class);
    }

    /** Build a minimal SM-format workbook and return its path. */
    private function fixtureWorkbook(): string
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        $sm1 = $book->createSheet(); $sm1->setTitle('SM.1');
        // Jan (row5), Feb (row6): C=rn, D/E/F=occ, G/H/I=arr, J/K/L=rev
        $sm1->fromArray([50, 0.90, 0.92, 0.80, 2000000, 2100000, 1900000, 100000000, 110000000, 95000000], null, 'C5');
        $sm1->fromArray([48, 0.88, 0.90, 0.78, 1950000, 2050000, 1850000, 93600000, 98400000, 88800000], null, 'C6');

        $sm2 = $book->createSheet(); $sm2->setTitle('SM.2');
        $sm2->setCellValue('B5', 'Booking.com'); $sm2->setCellValue('C5', 50); $sm2->setCellValue('F5', 150000000);
        $sm2->setCellValue('B6', 'Expedia'); $sm2->setCellValue('C6', 34); $sm2->setCellValue('F6', 115000000);
        $sm2->setCellValue('B7', 'Total Sold OTA'); $sm2->setCellValue('C7', 84); // must be skipped
        $sm2->setCellValue('B8', 'Agoda'); $sm2->setCellValue('C8', null);         // no production -> skipped

        $sm3 = $book->createSheet(); $sm3->setTitle('SM.3');
        $sm3->setCellValue('B5', 'Bed and breakfast'); $sm3->setCellValue('C5', 60); $sm3->setCellValue('F5', 180000000);

        $sm4 = $book->createSheet(); $sm4->setTitle('SM.4');
        $sm4->setCellValue('C1', 2026);           // year marker
        $sm4->setCellValue('B2', 'Source'); $sm4->setCellValue('C2', 'Month');
        $sm4->setCellValue('C3', 'Jan');          // month header (B empty -> skipped)
        $sm4->fromArray(['Booking.com', 10, 20, 30, 0, 0, 0, 0, 0, 0, 0, 0, 0], null, 'B4');
        $sm4->fromArray(['Expedia', 5, 5, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0], null, 'B5');
        // duplicate source to test merging
        $sm4->fromArray(['Booking.com', 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0], null, 'B6');

        $owner = $book->createSheet(); $owner->setTitle('OWNER OVERVIEW');
        $owner->setCellValue('C6', 'September 2026'); $owner->setCellValue('D6', 60); $owner->setCellValue('F6', 190000000);
        $owner->setCellValue('C7', 'October 2026'); $owner->setCellValue('D7', 28); $owner->setCellValue('F7', 67000000);
        $owner->setCellValue('C30', 'OTA'); $owner->setCellValue('D30', 84); $owner->setCellValue('G30', 265000000);
        $owner->setCellValue('C31', 'Direct'); $owner->setCellValue('D31', 21); $owner->setCellValue('G31', 52000000);

        $path = tempnam(sys_get_temp_dir(), 'bk_').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function test_parser_reads_sections_and_meta(): void
    {
        $path = $this->fixtureWorkbook();
        $data = (new WeeklyReportParser())->parse($path, 'BKDS_Weekly_Report_SM_02_Oct_-_08_Oct_2026.xlsx');

        $this->assertSame('BKDS', $data['meta']['property_code']);
        $this->assertSame('2026-10-02', $data['meta']['start_date']);
        $this->assertSame('2026-10-08', $data['meta']['end_date']);

        $this->assertCount(12, $data['sectionB']);                 // always 12 months
        $this->assertCount(2, $data['sectionC']);                   // Total + Agoda skipped
        $this->assertCount(1, $data['sectionD']);
        $this->assertCount(2, $data['ownerRepeater']);
        $this->assertCount(2, $data['ownerMix']);

        // 3 channel rows parsed (incl. the duplicate Booking.com).
        $this->assertCount(3, $data['channels']);
        $this->assertNotEmpty($data['warnings']);                   // Agoda skip noted
    }

    public function test_importer_applies_data_and_merges_duplicate_channels(): void
    {
        $path = $this->fixtureWorkbook();
        $data = (new WeeklyReportParser())->parse($path, 'BKDS_Weekly_Report_SM_02_Oct_-_08_Oct_2026.xlsx');

        $property = Property::where('code', 'BKDS')->firstOrFail();
        $week = ReportWeek::create([
            'property_id' => $property->id,
            'start_date' => '2026-10-02', 'end_date' => '2026-10-08',
            'year' => 2026, 'week_number' => 40, 'label' => '02 Oct – 08 Oct 2026',
            'status' => \App\Enums\ReportStatus::InProgress,
        ]);

        $summary = (new WeeklyReportImporter())->applyToWeek($data, $week);

        $this->assertSame(2, $summary['C (segments)']);
        // Duplicate Booking.com merged -> 2 distinct sources for 2026.
        $this->assertSame(2, $week->channelMonthRns()->where('year', 2026)->count());
        $booking = $week->channelMonthRns()->where('source_label', 'Booking.com')->first();
        $this->assertSame(11, $booking->jan); // 10 + 1 merged
        $this->assertSame(84, (int) $week->segmentProductions()->sum('rn_sold')); // 50 + 34
    }

    public function test_livewire_upload_preview_and_apply(): void
    {
        $this->actingAs(User::factory()->create());
        $path = $this->fixtureWorkbook();
        $file = UploadedFile::fake()->createWithContent(
            'BKDS_Weekly_Report_SM_02_Oct_-_08_Oct_2026.xlsx',
            file_get_contents($path),
        );

        Livewire::test(DataImport::class)
            ->set('file', $file)
            ->assertSet('step', 'preview')
            ->assertSet('propertyId', Property::where('code', 'BKDS')->value('id'))
            ->call('apply')
            ->assertSet('step', 'done');

        $this->assertDatabaseHas('report_weeks', ['property_id' => Property::where('code', 'BKDS')->value('id'), 'start_date' => '2026-10-02 00:00:00']);
        $this->assertDatabaseHas('imports', ['status' => 'applied', 'original_filename' => 'BKDS_Weekly_Report_SM_02_Oct_-_08_Oct_2026.xlsx']);
    }
}

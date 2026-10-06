<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Livewire\VhpSegmentImport;
use App\Models\MonthlyStat;
use App\Models\Property;
use App\Models\ReportWeek;
use App\Models\SegmentProduction;
use App\Models\User;
use App\Services\Vhp\VhpReservationCsvParser;
use App\Services\WeeklyReportImporter;
use Database\Seeders\PropertySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class VhpSegmentImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PropertySeeder::class);
        $this->actingAs(User::factory()->create());
    }

    /** A minimal VHP "Reservation By Creation Date" CSV (BLUE KARMA VILLAGE). */
    private function vhpCsv(): string
    {
        $header = ['No', 'Created Date', 'Reservation Number', 'Reservation Name', 'Arrival', 'Departure', 'Room Number', 'Room Quantity', 'Night', 'Room Type', 'Nationality', 'Adult', 'Child', 'Compliment', 'Arrangement', 'Rate Code', 'Room Rate', 'Total Revenue', 'Guest Name', 'Segment', 'Voucher No', 'SOB', 'Status', 'Created By', 'Created Id', 'Last Changed Date', 'Changed By'];

        // [No, ReservationName, Qty, Night, TotalRevenue, Segment, SOB, Status]
        $spec = [
            [1, 'BOOKING.COM  ', 1, 3, '9,000,000.00', 'FIT-OTA', 'OTA', 'Departed'],
            [2, 'BOOKING.COM  ', 1, 2, '6,000,000.00', 'FIT-OTA', 'OTA', 'Guaranted'],
            [3, 'EXPEDIA ', 1, 2, '4,000,000.00', 'FIT-OTA', 'OTA', 'Guaranted'],
            [4, 'MG HOLIDAY , ', 1, 3, '7,500,000.00', 'B2B', 'OTA', 'Departed'],
            [5, 'LUXURY ESCAPE , ', 1, 7, '20,000,000.00', 'OVS-TA', 'Offline & TA', 'Guaranted'],
            [6, 'Blogger / Influencer, ', 1, 2, '0.00', 'COMPLIMENT', 'RSV by Phone', 'Guaranted'],
            [7, 'BOOKING.COM  ', 1, 4, '99,999,999.00', 'FIT-OTA', 'OTA', 'Cancelled'], // excluded
            [8, 'EXPEDIA ', 0, 0, '0.00', 'FIT-OTA', 'OTA', 'AccGuest'],                // 0 contribution
        ];

        $lines = [];
        $lines[] = 'BLUE KARMA VILLAGE';
        $lines[] = 'Jl. Umalas,,,,,,,,,,,,Date: 06/10/2026';
        $lines[] = '';
        $lines[] = 'Tel,,,,,,,,,,,,Period: 25/09/26 - 01/10/26';
        $lines[] = 'Reservation By Creation Date';
        $lines[] = '';
        $lines[] = '"'.implode('","', $header).'"';
        foreach ($spec as $r) {
            $row = array_fill(0, count($header), '');
            $row[0] = $r[0];
            $row[1] = '2026-09-25';
            $row[3] = $r[1];
            $row[7] = $r[2];
            $row[8] = $r[3];
            $row[17] = $r[4];
            $row[19] = $r[5];
            $row[21] = $r[6];
            $row[22] = $r[7];

            $lines[] = '"'.implode('","', $row).'"';
        }

        return implode("\n", $lines)."\n";
    }

    private function csvFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('Reservation_by_Creation_Date.csv', $this->vhpCsv());
    }

    public function test_parser_groups_by_segment_and_excludes_cancelled(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'vhp_').'.csv';
        file_put_contents($path, $this->vhpCsv());

        $out = (new VhpReservationCsvParser)->parse($path);

        $this->assertSame('BLUE KARMA VILLAGE', $out['meta']['property_name']);
        $this->assertSame('2026-09-25', $out['meta']['start_date']);

        $segs = collect($out['segments']);
        // Booking.com aggregated across its two non-cancelled rows: 5 RN / 15M.
        $booking = $segs->firstWhere('label', 'BOOKING.COM');
        $this->assertSame('OTA', $booking['segment_group']);
        $this->assertSame(5, $booking['rn_sold']);
        $this->assertEqualsWithDelta(15_000_000, $booking['gross_revenue'], 0.01);

        // MG Holiday sits in B2B (from VHP's Segment column), not OTA.
        $this->assertSame('B2B', $segs->firstWhere('label', 'MG HOLIDAY')['segment_group']);
        // Luxury Escape → Offline TA.
        $this->assertSame('Offline TA', $segs->firstWhere('label', 'LUXURY ESCAPE')['segment_group']);
        // Compliment kept as its own group.
        $this->assertSame('Compliment', $segs->firstWhere('label', 'Blogger / Influencer')['segment_group']);

        // Cancelled Booking.com row excluded; totals = 5+2+3+7+2 = 19 RN.
        $this->assertSame(19, $segs->sum('rn_sold'));
        $this->assertStringContainsString('cancelled', strtolower(implode(' ', $out['warnings'])));
    }

    public function test_apply_segments_replaces_only_section_c(): void
    {
        $property = Property::where('code', 'BKV')->firstOrFail();
        $week = ReportWeek::create([
            'property_id' => $property->id, 'start_date' => '2026-09-25', 'end_date' => '2026-10-01',
            'year' => 2026, 'week_number' => 39, 'label' => 'wk', 'status' => ReportStatus::InProgress,
        ]);
        // Pre-existing data in another section + a stale Section C row.
        MonthlyStat::create(['report_week_id' => $week->id, 'month' => 10, 'rn_sold' => 361, 'occ_actual' => 0.7763]);
        SegmentProduction::create(['report_week_id' => $week->id, 'label' => 'STALE', 'rn_sold' => 999, 'sort_order' => 0]);

        $parsed = (new VhpReservationCsvParser)->parse(tap(tempnam(sys_get_temp_dir(), 'vhp_').'.csv', fn ($p) => file_put_contents($p, $this->vhpCsv())));
        (new WeeklyReportImporter)->applySegments($parsed['segments'], $week);

        // Section C replaced (stale gone, grouped rows in), Section B untouched.
        $this->assertDatabaseMissing('segment_productions', ['report_week_id' => $week->id, 'label' => 'STALE']);
        $this->assertDatabaseHas('segment_productions', ['report_week_id' => $week->id, 'label' => 'MG HOLIDAY', 'segment_group' => 'B2B']);
        $this->assertSame(1, $week->monthlyStats()->count());
        $this->assertSame(361, $week->monthlyStats()->first()->rn_sold);
    }

    public function test_livewire_upload_applies_section_c(): void
    {
        $bkv = Property::where('code', 'BKV')->firstOrFail();

        Livewire::test(VhpSegmentImport::class)
            ->set('file', $this->csvFile())
            ->assertSet('step', 'preview')
            ->assertSet('propertyId', $bkv->id)   // auto-detected from the CSV header
            ->assertSet('startDate', '2026-09-25')
            ->call('apply')
            ->assertSet('step', 'done');

        $this->assertDatabaseHas('report_weeks', ['property_id' => $bkv->id, 'start_date' => '2026-09-25 00:00:00']);
        $this->assertDatabaseHas('imports', ['source' => 'vhp', 'status' => 'applied']);
        $week = ReportWeek::where('property_id', $bkv->id)->firstOrFail();
        $this->assertSame(19, (int) $week->segmentProductions()->sum('rn_sold'));
        $this->assertSame('OTA', $week->segmentProductions()->where('label', 'BOOKING.COM')->value('segment_group'));
    }
}

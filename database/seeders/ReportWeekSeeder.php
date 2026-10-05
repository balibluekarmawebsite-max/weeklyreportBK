<?php

namespace Database\Seeders;

use App\Enums\ReportStatus;
use App\Models\Property;
use App\Models\ReportWeek;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * A few sample weeks (Fri–Thu, as in the sample report) so the list and
 * dashboard have content to show. Real weeks are created from Phase 2 onward.
 */
class ReportWeekSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::where('code', 'BKDS')->first();
        if (! $property) {
            return;
        }

        $owner = User::where('email', 'ota@bluekarmasecrets.com')->first();

        // Most recent completed Friday as the start of "this week".
        $thisWeekStart = Carbon::today()->previous(Carbon::FRIDAY);

        $samples = [
            [$thisWeekStart->copy(), ReportStatus::InProgress],
            [$thisWeekStart->copy()->subWeek(), ReportStatus::Approved],
            [$thisWeekStart->copy()->subWeeks(2), ReportStatus::Exported],
        ];

        foreach ($samples as [$start, $status]) {
            $end = $start->copy()->addDays(6);

            ReportWeek::updateOrCreate(
                ['property_id' => $property->id, 'start_date' => $start->toDateString()],
                [
                    'end_date' => $end->toDateString(),
                    'year' => (int) $start->isoFormat('GGGG'),
                    'week_number' => (int) $start->isoFormat('W'),
                    'label' => $start->format('d M').' – '.$end->format('d M Y'),
                    'status' => $status,
                    'owner_id' => $owner?->id,
                    'locked_at' => $status->isLocked() ? now() : null,
                    'exported_at' => $status === ReportStatus::Exported ? now() : null,
                ],
            );
        }
    }
}

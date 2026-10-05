<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\ReportWeek;
use Carbon\Carbon;

/**
 * Shared logic for turning a parsed weekly-report workbook into a stored week,
 * used by both the manual Data Import screen and the VHP robot endpoint so they
 * behave identically: weeks run Friday–Thursday, a locked week is never
 * overwritten, and applying replaces the week's section data.
 */
class WeeklyImportPipeline
{
    /**
     * Find (or create) the report week a given start date belongs to.
     *
     * The week is anchored to the Friday on/before $startDate (reports run
     * Fri–Thu). Returns the week plus whether it was created and whether it is
     * locked (caller decides how to refuse a locked week).
     *
     * @return array{week: ReportWeek, created: bool, locked: bool}
     */
    public function findOrCreateWeek(int $propertyId, string $startDate, ?int $ownerId = null): array
    {
        $start = Carbon::parse($startDate);
        if (! $start->isFriday()) {
            $start = $start->previous(Carbon::FRIDAY);
        }
        $end = $start->copy()->addDays(6);

        $week = ReportWeek::where('property_id', $propertyId)
            ->whereDate('start_date', $start->toDateString())
            ->first();

        if ($week) {
            return ['week' => $week, 'created' => false, 'locked' => $week->isLocked()];
        }

        $week = ReportWeek::create([
            'property_id' => $propertyId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'year' => (int) $start->isoFormat('GGGG'),
            'week_number' => (int) $start->isoFormat('W'),
            'label' => $start->format('d M').' – '.$end->format('d M Y'),
            'status' => ReportStatus::InProgress,
            'owner_id' => $ownerId,
        ]);

        return ['week' => $week, 'created' => true, 'locked' => false];
    }
}

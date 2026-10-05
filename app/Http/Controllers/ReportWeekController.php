<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Models\ActivityLog;
use App\Models\Property;
use App\Models\ReportWeek;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportWeekController extends Controller
{
    public function index(): View
    {
        $property = \App\Support\Workspace::currentProperty();

        $weeks = ReportWeek::query()
            ->when($property, fn ($q) => $q->where('property_id', $property->id))
            ->with('owner')
            ->orderByDesc('start_date')
            ->paginate(20);

        return view('reports.index', [
            'property' => $property,
            'weeks' => $weeks,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'start_date' => ['required', 'date'],
        ]);

        // The report week runs Friday → Thursday. Snap the chosen date back to
        // its Friday so weeks always align.
        $start = Carbon::parse($data['start_date']);
        if (! $start->isFriday()) {
            $start = $start->previous(Carbon::FRIDAY);
        }
        $end = $start->copy()->addDays(6);

        $week = ReportWeek::where('property_id', $data['property_id'])
            ->whereDate('start_date', $start->toDateString())
            ->first();

        if (! $week) {
            $week = ReportWeek::create([
                'property_id' => $data['property_id'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'year' => (int) $start->isoFormat('GGGG'),
                'week_number' => (int) $start->isoFormat('W'),
                'label' => $start->format('d M').' – '.$end->format('d M Y'),
                'status' => ReportStatus::Draft,
                'owner_id' => $request->user()->id,
            ]);
            ActivityLog::record('created', $week, 'Created report week '.$week->label);
        }

        return redirect()->route('reports.show', $week);
    }

    public function show(ReportWeek $reportWeek): View
    {
        $reportWeek->load('property', 'owner');

        // Which sections already have content (for the outline ticks).
        $counts = [
            'A' => $reportWeek->overviewBlocks()->whereNotNull('body')->where('body', '!=', '')->count(),
            'B' => $reportWeek->monthlyStats()->count(),
            'C' => $reportWeek->segmentProductions()->count(),
            'D' => $reportWeek->rateCodeProductions()->count(),
            'EF' => $reportWeek->channelMonthRns()->count(),
            'G' => $reportWeek->activities()->where('department', 'sales')->count(),
            'G2' => $reportWeek->activities()->where('department', 'ecommerce')->count(),
            'H' => $reportWeek->socialMediaMetrics()->whereNotNull('this_week')->count(),
            'I' => $reportWeek->trainings()->count(),
            'J' => $reportWeek->actionPlans()->count(),
            'OWNER' => $reportWeek->ownerRepeaterMonths()->count() + $reportWeek->ownerChannelMix()->count(),
        ];

        $section = request('section', 'B');

        return view('reports.show', [
            'week' => $reportWeek,
            'counts' => $counts,
            'section' => $section,
        ]);
    }
}

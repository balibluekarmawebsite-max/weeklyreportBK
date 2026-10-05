<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\ReportWeek;
use Illuminate\Contracts\View\View;

class ReportWeekController extends Controller
{
    public function index(): View
    {
        $property = Property::where('is_active', true)->orderBy('id')->first();

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

    public function show(ReportWeek $reportWeek): View
    {
        $reportWeek->load('property', 'owner');

        return view('reports.show', [
            'week' => $reportWeek,
        ]);
    }
}

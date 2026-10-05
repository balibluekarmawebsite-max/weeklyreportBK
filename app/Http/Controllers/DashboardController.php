<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\ReportWeek;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $property = Property::where('is_active', true)->orderBy('id')->first();

        $latestWeek = $property
            ? $property->reportWeeks()->latest('start_date')->first()
            : null;

        $recentWeeks = $property
            ? $property->reportWeeks()->latest('start_date')->take(5)->get()
            : collect();

        return view('dashboard', [
            'property' => $property,
            'latestWeek' => $latestWeek,
            'recentWeeks' => $recentWeeks,
        ]);
    }
}

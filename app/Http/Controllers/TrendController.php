<?php

namespace App\Http\Controllers;

use App\Support\TrendService;
use App\Support\Workspace;
use Illuminate\Contracts\View\View;

class TrendController extends Controller
{
    /** Week-over-week trends for the current property + a portfolio snapshot. */
    public function index(): View
    {
        $property = Workspace::currentProperty();

        $series = $property ? TrendService::series($property, 8) : collect();

        // Chart payload: labels + one array per metric, oldest week first.
        $chart = [
            'labels' => $series->pluck('short')->all(),
            'occ' => $series->map(fn ($r) => $r['kpis']['occ'])->all(),
            'adr' => $series->map(fn ($r) => $r['kpis']['adr'])->all(),
            'rev' => $series->map(fn ($r) => $r['kpis']['rev'])->all(),
            'rn' => $series->map(fn ($r) => $r['kpis']['rn'])->all(),
        ];

        return view('trends', [
            'property' => $property,
            'series' => $series,
            'comparison' => $property ? TrendService::comparison($property) : null,
            'portfolio' => TrendService::portfolio(),
            'chart' => $chart,
        ]);
    }
}

<x-app-layout>
    <x-slot name="title">Trends</x-slot>
    <x-slot name="header">
        <div class="flex items-end justify-between">
            <div class="flex items-center gap-4">
                @if($property)
                    <div class="hidden rounded-xl border border-sand-200 bg-white px-3 py-2 sm:flex">
                        <x-property-logo :property="$property" class="h-10" />
                    </div>
                @endif
                <div>
                    <h1 class="font-serif text-2xl font-semibold text-ink-900">Trends</h1>
                    <p class="mt-1 text-sm text-ink-500">{{ $property?->name ?? 'No property configured' }} · week over week</p>
                </div>
            </div>
        </div>
    </x-slot>

    @php
        // Per-metric display rules for the week-over-week cards.
        $cards = [
            ['key' => 'occ', 'label' => 'Occupancy', 'fmt' => fn ($v) => $v === null ? '–' : \App\Support\Format::percent($v), 'unit' => 'pp', 'goodUp' => true],
            ['key' => 'adr', 'label' => 'ADR', 'fmt' => fn ($v) => \App\Support\Format::idr($v), 'unit' => '%', 'goodUp' => true],
            ['key' => 'rev', 'label' => 'Room Revenue', 'fmt' => fn ($v) => \App\Support\Format::idr($v), 'unit' => '%', 'goodUp' => true],
            ['key' => 'rn', 'label' => 'RN Sold', 'fmt' => fn ($v) => \App\Support\Format::number($v), 'unit' => '%', 'goodUp' => true],
        ];
    @endphp

    @if(!$property || $series->isEmpty())
        <div class="card p-10 text-center text-sm text-ink-500">
            No weeks yet for this property. Create or import a week to see trends.
        </div>
    @else
        {{-- Week-over-week comparison --}}
        @if($comparison)
            <div class="mb-3 flex flex-wrap items-baseline gap-x-2 text-xs font-medium uppercase tracking-wide text-gold-500">
                <span>This week vs last week</span>
                <span class="normal-case tracking-normal text-ink-400">
                    {{ $comparison['current']->label }}
                    @if($comparison['previous']) <span class="text-ink-300">vs</span> {{ $comparison['previous']->label }} @endif
                </span>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($cards as $c)
                    @php
                        $cur = $comparison['cur'][$c['key']] ?? null;
                        $d = $comparison['metrics'][$c['key']];
                        $dir = $d['direction'];
                        $tone = $dir === 'up' ? 'text-emerald-600 bg-emerald-50' : ($dir === 'down' ? 'text-red-600 bg-red-50' : 'text-ink-400 bg-sand-100');
                        $arrow = $dir === 'up' ? '▲' : ($dir === 'down' ? '▼' : '—');
                        // Occupancy moves in percentage points; the rest as % change.
                        $deltaLabel = $c['unit'] === 'pp'
                            ? ($d['abs'] === null ? null : \App\Support\Format::number(abs($d['abs']), 1).' pp')
                            : ($d['pct'] === null ? null : \App\Support\Format::number(abs($d['pct']), 1).'%');
                    @endphp
                    <div class="card p-5">
                        <div class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $c['label'] }}</div>
                        <div class="mt-1 font-serif text-2xl font-semibold text-ink-900 tabular-nums">{{ $c['fmt']($cur) }}</div>
                        <div class="mt-2 flex items-center gap-2 text-xs">
                            @if($deltaLabel)
                                <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-medium {{ $tone }}">{{ $arrow }} {{ $deltaLabel }}</span>
                                <span class="text-ink-400">vs last week</span>
                            @else
                                <span class="text-ink-300">no prior week</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Trend charts over the last weeks --}}
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="card p-6">
                <h2 class="font-serif text-lg font-semibold text-ink-900">Occupancy trend</h2>
                <p class="mt-1 text-xs text-ink-500">Headline occupancy, last {{ $series->count() }} week(s).</p>
                <div class="mt-4 h-60"><canvas id="trendOcc"></canvas></div>
            </div>
            <div class="card p-6">
                <h2 class="font-serif text-lg font-semibold text-ink-900">Room revenue trend</h2>
                <p class="mt-1 text-xs text-ink-500">Headline room revenue, last {{ $series->count() }} week(s).</p>
                <div class="mt-4 h-60"><canvas id="trendRev"></canvas></div>
            </div>
        </div>

        {{-- Weekly figures table --}}
        <div class="card mt-6 overflow-hidden">
            <div class="border-b border-sand-200 px-5 py-4">
                <h2 class="font-serif text-lg font-semibold text-ink-900">Week by week</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                            <th class="px-5 py-2 font-medium">Week</th>
                            <th class="px-5 py-2 text-right font-medium">Occupancy</th>
                            <th class="px-5 py-2 text-right font-medium">ADR</th>
                            <th class="px-5 py-2 text-right font-medium">Room Revenue</th>
                            <th class="px-5 py-2 text-right font-medium">RN Sold</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach($series->reverse() as $row)
                            <tr class="hover:bg-sand-50">
                                <td class="px-5 py-2.5 font-medium text-ink-800">
                                    <a href="{{ route('reports.show', $row['week']) }}" wire:navigate class="hover:text-ink-600 hover:underline">{{ $row['label'] }}</a>
                                </td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-ink-700">{{ $row['kpis']['occ'] === null ? '–' : \App\Support\Format::percent($row['kpis']['occ']) }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-ink-700">{{ \App\Support\Format::idr($row['kpis']['adr']) }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-ink-700">{{ \App\Support\Format::idr($row['kpis']['rev']) }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-ink-700">{{ \App\Support\Format::number($row['kpis']['rn']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Portfolio snapshot — all properties, latest week --}}
        <div class="card mt-6 overflow-hidden">
            <div class="border-b border-sand-200 px-5 py-4">
                <h2 class="font-serif text-lg font-semibold text-ink-900">Portfolio · latest week per property</h2>
                <p class="mt-1 text-xs text-ink-500">Headline figures for each property's most recent report week.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                            <th class="px-5 py-2 font-medium">Property</th>
                            <th class="px-5 py-2 font-medium">Latest week</th>
                            <th class="px-5 py-2 text-right font-medium">Occupancy</th>
                            <th class="px-5 py-2 text-right font-medium">ADR</th>
                            <th class="px-5 py-2 text-right font-medium">Room Revenue</th>
                            <th class="px-5 py-2 text-right font-medium">RN Sold</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach($portfolio as $row)
                            <tr class="hover:bg-sand-50 {{ $property && $row['property']->id === $property->id ? 'bg-sand-50/60' : '' }}">
                                <td class="px-5 py-2.5 font-medium text-ink-800">
                                    {{ $row['property']->name }}
                                    <span class="ml-1 text-xs text-ink-400">{{ $row['property']->code }}</span>
                                </td>
                                <td class="px-5 py-2.5 text-ink-600">{{ $row['week']?->label ?? '–' }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-ink-700">{{ $row['kpis']['occ'] === null ? '–' : \App\Support\Format::percent($row['kpis']['occ']) }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-ink-700">{{ \App\Support\Format::idr($row['kpis']['adr']) }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-ink-700">{{ \App\Support\Format::idr($row['kpis']['rev']) }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-ink-700">{{ \App\Support\Format::number($row['kpis']['rn']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Chart data + render --}}
        <script type="application/json" id="trendData">@json($chart)</script>
        <script>
        window.addEventListener('load', function () {
            if (!window.Chart) return;
            const D = JSON.parse(document.getElementById('trendData').textContent);
            const INK = '#124E68', GRID = '#E9E2D2', BLUE = '#206E8F';
            const gridCfg = { grid: { color: GRID, drawBorder: false }, ticks: { color: INK } };

            new window.Chart(document.getElementById('trendOcc'), {
                type: 'line',
                data: { labels: D.labels, datasets: [
                    { label: 'Occupancy', data: D.occ, borderColor: BLUE, backgroundColor: BLUE, borderWidth: 2.5, tension: 0.3, spanGaps: true, pointRadius: 3 },
                ]},
                options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => window.bkFmt.pct(c.parsed.y) } } },
                    scales: { y: { ...gridCfg, ticks: { ...gridCfg.ticks, callback: v => v + '%' } }, x: gridCfg } }
            });

            new window.Chart(document.getElementById('trendRev'), {
                type: 'bar',
                data: { labels: D.labels, datasets: [
                    { label: 'Room revenue', data: D.rev, backgroundColor: BLUE, borderRadius: 3 },
                ]},
                options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => window.bkFmt.idr(c.parsed.y) } } },
                    scales: { y: { ...gridCfg, ticks: { ...gridCfg.ticks, callback: v => (v/1e9).toFixed(1) + 'B' } }, x: gridCfg } }
            });
        });
        </script>
    @endif
</x-app-layout>

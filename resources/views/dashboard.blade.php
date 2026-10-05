<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <div class="flex items-end justify-between">
            <div>
                <h1 class="font-serif text-2xl font-semibold text-ink-900">Dashboard</h1>
                <p class="mt-1 text-sm text-ink-500">
                    {{ $property?->name ?? 'No property configured' }}
                    @if($week) · <span class="text-ink-600">{{ $week->label }}</span>@endif
                    @if($currentMonthLabel) · figures for <span class="font-medium text-ink-700">{{ $currentMonthLabel }}</span>@endif
                </p>
            </div>
            @if($week)
                <a href="{{ route('reports.show', $week) }}" wire:navigate class="hidden rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-ink-700 hover:bg-sand-100 sm:inline-flex">Open this week</a>
            @endif
        </div>
    </x-slot>

    @if(!$week)
        <div class="card p-10 text-center text-sm text-ink-500">No report week yet. Import a file or create a week to see the dashboard.</div>
    @else
        {{-- KPI cards — all figures are for the reporting month --}}
        @php $m = $currentMonthLabel ?? 'this month'; @endphp
        <div class="mb-3 text-xs font-medium uppercase tracking-wide text-gold-500">Key figures · {{ $m }} {{ $week->year }}</div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-kpi-card :label="'Occupancy · '.$m" :value="$kpis['occupancy']['value'] ?? '–'" sub="vs budget" :variance="$kpis['occupancy']['var'] ?? null" />
            <x-kpi-card :label="'ADR · '.$m" :value="$kpis['adr']['value'] ?? '–'" sub="vs budget" :variance="$kpis['adr']['var'] ?? null" />
            <x-kpi-card :label="'Room Revenue · '.$m" :value="$kpis['revenue']['value'] ?? '–'" sub="vs budget" :variance="$kpis['revenue']['var'] ?? null" />
            <x-kpi-card :label="'RN Sold · '.$m" :value="$kpis['rn']['value'] ?? '–'" :sub="$m" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Occupancy line chart --}}
            <div class="card p-6 lg:col-span-2">
                <h2 class="font-serif text-lg font-semibold text-ink-900">Occupancy · Actual vs Budget vs Last Year</h2>
                <p class="mt-1 text-xs text-ink-500">Percent, month by month.</p>
                <div class="mt-4 h-64"><canvas id="chartOccupancy"></canvas></div>
            </div>

            {{-- Report progress --}}
            <div class="card p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-serif text-lg font-semibold text-ink-900">Report progress</h2>
                    <span class="text-sm font-medium text-ink-600 tabular-nums">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
                </div>
                @php $pct = $progress['total'] ? round($progress['done'] / $progress['total'] * 100) : 0; @endphp
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-sand-200">
                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                </div>
                <ul class="mt-4 space-y-1.5 text-sm">
                    @foreach($progress['sections'] as $name => $done)
                        <li class="flex items-center justify-between">
                            <span class="text-ink-700">{{ $name }}</span>
                            @if($done)
                                <span class="badge bg-emerald-100 text-emerald-700">Done</span>
                            @else
                                <span class="badge bg-slate-100 text-slate-500">Pending</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Revenue bars --}}
            <div class="card p-6">
                <h2 class="font-serif text-lg font-semibold text-ink-900">Room Revenue · Actual vs Budget vs Last Year</h2>
                <p class="mt-1 text-xs text-ink-500">IDR, month by month.</p>
                <div class="mt-4 h-64"><canvas id="chartRevenue"></canvas></div>
            </div>

            {{-- Channel mix ranked bars --}}
            <div class="card p-6">
                <h2 class="font-serif text-lg font-semibold text-ink-900">Channel Mix · this week</h2>
                <p class="mt-1 text-xs text-ink-500">Room nights by source (Section C).</p>
                <div class="mt-4 h-64"><canvas id="chartChannel"></canvas></div>
            </div>
        </div>

        {{-- Recent weeks --}}
        <div class="card mt-6">
            <div class="flex items-center justify-between border-b border-sand-200 px-6 py-4">
                <h2 class="font-serif text-lg font-semibold text-ink-900">Recent weeks</h2>
                <a href="{{ route('reports.index') }}" wire:navigate class="text-sm font-medium text-ink-600 hover:text-ink-800">See all →</a>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-sand-100">
                    @foreach($recentWeeks as $w)
                        <tr class="hover:bg-sand-50">
                            <td class="px-6 py-3"><a href="{{ route('reports.show', $w) }}" wire:navigate class="font-medium text-ink-800 hover:underline">{{ $w->label }}</a></td>
                            <td class="px-6 py-3 text-ink-600">{{ $w->owner?->name ?? '–' }}</td>
                            <td class="px-6 py-3"><x-status-badge :status="$w->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Chart data + render --}}
        <script type="application/json" id="chartData">@json($charts)</script>
        <script>
        window.addEventListener('load', function () {
            if (!window.Chart) return;
            const D = JSON.parse(document.getElementById('chartData').textContent);
            const INK = '#154748', GRID = '#E9E2D2', ACTUAL = '#2a78d6', BUDGET = '#eb6834', LY = '#1baf7a';
            const gridCfg = { grid: { color: GRID, drawBorder: false }, ticks: { color: INK } };

            // Occupancy line
            new window.Chart(document.getElementById('chartOccupancy'), {
                type: 'line',
                data: { labels: D.months, datasets: [
                    { label: 'Actual', data: D.occupancy.actual, borderColor: ACTUAL, backgroundColor: ACTUAL, borderWidth: 2.5, tension: 0.3, spanGaps: true, pointRadius: 2 },
                    { label: 'Budget', data: D.occupancy.budget, borderColor: BUDGET, backgroundColor: BUDGET, borderWidth: 2, borderDash: [5,4], tension: 0.3, spanGaps: true, pointRadius: 0 },
                    { label: 'Last Year', data: D.occupancy.ly, borderColor: LY, backgroundColor: LY, borderWidth: 2, tension: 0.3, spanGaps: true, pointRadius: 0 },
                ]},
                options: { interaction: { mode: 'index', intersect: false },
                    plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + window.bkFmt.pct(c.parsed.y) } } },
                    scales: { y: { ...gridCfg, ticks: { ...gridCfg.ticks, callback: v => v + '%' } }, x: gridCfg } }
            });

            // Revenue grouped bars
            new window.Chart(document.getElementById('chartRevenue'), {
                type: 'bar',
                data: { labels: D.months, datasets: [
                    { label: 'Actual', data: D.revenue.actual, backgroundColor: ACTUAL, borderRadius: 3 },
                    { label: 'Budget', data: D.revenue.budget, backgroundColor: BUDGET, borderRadius: 3 },
                    { label: 'Last Year', data: D.revenue.ly, backgroundColor: LY, borderRadius: 3 },
                ]},
                options: { plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + window.bkFmt.idr(c.parsed.y) } } },
                    scales: { y: { ...gridCfg, ticks: { ...gridCfg.ticks, callback: v => (v/1e9).toFixed(1) + 'B' } }, x: gridCfg } }
            });

            // Channel mix ranked bars (single hue)
            new window.Chart(document.getElementById('chartChannel'), {
                type: 'bar',
                data: { labels: D.channelMix.labels, datasets: [
                    { label: 'Room nights', data: D.channelMix.rn, backgroundColor: ACTUAL, borderRadius: 3 },
                ]},
                options: { indexAxis: 'y',
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => window.bkFmt.idr(c.parsed.x) + ' RN · ' + window.bkFmt.pct(D.channelMix.pct[c.dataIndex]) } } },
                    scales: { x: gridCfg, y: { ...gridCfg, grid: { display: false } } } }
            });
        });
        </script>
    @endif
</x-app-layout>

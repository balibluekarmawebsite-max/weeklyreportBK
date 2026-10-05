<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <div class="flex items-end justify-between">
            <div>
                <h1 class="font-serif text-2xl font-semibold text-ink-900">Dashboard</h1>
                <p class="mt-1 text-sm text-ink-500">{{ $property?->name ?? 'No property configured' }}</p>
            </div>
            <a href="{{ route('reports.index') }}" wire:navigate class="hidden rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-ink-700 hover:bg-sand-100 sm:inline-flex">
                View all weeks
            </a>
        </div>
    </x-slot>

    {{-- KPI cards. Values are placeholders until Phase 2 (data entry) & Phase 5 (dashboard). --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card label="Occupancy" value="–" sub="vs budget" />
        <x-kpi-card label="ADR" value="–" sub="vs last year" />
        <x-kpi-card label="Room Revenue" value="–" sub="vs budget" />
        <x-kpi-card label="ROAS" value="–" sub="this week" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Charts placeholder --}}
        <div class="card p-6 lg:col-span-2">
            <h2 class="font-serif text-lg font-semibold text-ink-900">Occupancy · ADR · Revenue</h2>
            <p class="mt-1 text-sm text-ink-500">Actual vs Budget vs Last Year, month by month.</p>
            <div class="mt-6 flex h-56 items-center justify-center rounded-lg border border-dashed border-sand-300 bg-sand-50 text-sm text-ink-400">
                Charts arrive in Phase 5 (Chart.js)
            </div>
        </div>

        {{-- Report progress --}}
        <div class="card p-6">
            <h2 class="font-serif text-lg font-semibold text-ink-900">This week</h2>
            @if($latestWeek)
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-sm text-ink-600">{{ $latestWeek->label ?? $latestWeek->start_date->format('d M') }}</span>
                    <x-status-badge :status="$latestWeek->status" />
                </div>
            @else
                <p class="mt-3 text-sm text-ink-500">No report week created yet. Weeks are created in Phase 1+ and filled from Phase 2 onward.</p>
            @endif

            <h3 class="mt-6 text-xs font-semibold uppercase tracking-wide text-ink-400">Section progress</h3>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach(['A — Overview', 'B — YTD Actual & Forecast', 'C — Segment Production', 'D — Rate Codes', 'E/F — Channels', 'G — Sales Activity', 'H — Social Media', 'I — Training', 'J — Action Plan'] as $section)
                    <li class="flex items-center justify-between">
                        <span class="text-ink-700">{{ $section }}</span>
                        <span class="badge bg-slate-100 text-slate-600">Pending</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Recent weeks --}}
    <div class="card mt-6">
        <div class="flex items-center justify-between border-b border-sand-200 px-6 py-4">
            <h2 class="font-serif text-lg font-semibold text-ink-900">Recent weeks</h2>
            <a href="{{ route('reports.index') }}" wire:navigate class="text-sm font-medium text-ink-600 hover:text-ink-800">See all →</a>
        </div>
        @if($recentWeeks->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-ink-500">No weeks yet.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-ink-400">
                        <th class="px-6 py-3 font-medium">Period</th>
                        <th class="px-6 py-3 font-medium">Owner</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach($recentWeeks as $week)
                        <tr class="hover:bg-sand-50">
                            <td class="px-6 py-3">
                                <a href="{{ route('reports.show', $week) }}" wire:navigate class="font-medium text-ink-800 hover:underline">{{ $week->label ?? $week->start_date->format('d M Y') }}</a>
                            </td>
                            <td class="px-6 py-3 text-ink-600">{{ $week->owner?->name ?? '–' }}</td>
                            <td class="px-6 py-3"><x-status-badge :status="$week->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-app-layout>

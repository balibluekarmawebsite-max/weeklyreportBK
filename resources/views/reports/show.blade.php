<x-app-layout>
    <x-slot name="title">{{ $week->label ?? $week->start_date->format('d M Y') }}</x-slot>
    <x-slot name="header">
        <div class="flex items-end justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="font-serif text-2xl font-semibold text-ink-900">{{ $week->label ?? $week->start_date->format('d M Y') }}</h1>
                    <x-status-badge :status="$week->status" />
                </div>
                <p class="mt-1 text-sm text-ink-500">{{ $week->property?->name }} · Owner: {{ $week->owner?->name ?? '–' }}</p>
            </div>
            <a href="{{ route('reports.index') }}" wire:navigate class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-ink-700 hover:bg-sand-100">← All weeks</a>
        </div>
    </x-slot>

    @php
        $outline = [
            ['A','A','Overview','phase'],
            ['B','B','YTD Actual & Forecast','data'],
            ['C','C','Market Segment','data'],
            ['D','D','Rate Code / Promotion','data'],
            ['EF','E/F','Channel Inside','data'],
            ['G','G','Sales Activity','phase'],
            ['G2','G2','E-commerce','phase'],
            ['H','H','Social Media','phase'],
            ['I','I','Training','phase'],
            ['J','J','Action Plan','phase'],
            ['OWNER','★','Owner Overview','data'],
        ];
    @endphp

    @if($week->isLocked())
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
            This report is <strong>{{ $week->status->label() }}</strong> and locked. Open an in-progress week to edit.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[220px_1fr]">
        {{-- Section outline --}}
        <aside class="card h-max p-3">
            <div class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">Sections</div>
            <nav class="space-y-0.5">
                @foreach($outline as [$key,$letter,$name,$type])
                    @php $active = $section === $key; $done = ($counts[$key] ?? 0) > 0; @endphp
                    <a href="{{ route('reports.show', $week) }}?section={{ $key }}" wire:navigate
                       class="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm transition {{ $active ? 'bg-ink-700 text-white' : 'text-ink-700 hover:bg-sand-100' }}">
                        <span class="flex items-center gap-2">
                            <span class="inline-flex h-5 w-7 items-center justify-center rounded text-xs font-semibold {{ $active ? 'bg-white/20 text-white' : 'bg-sand-100 text-ink-500' }}">{{ $letter }}</span>
                            <span>{{ $name }}</span>
                        </span>
                        @if($type === 'data')
                            <span class="h-2 w-2 rounded-full {{ $done ? 'bg-emerald-400' : 'bg-sand-300' }}" title="{{ $done ? 'Has data' : 'Empty' }}"></span>
                        @else
                            <span class="text-[10px] uppercase text-ink-300">later</span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- Active section --}}
        <div class="min-w-0">
            @switch($section)
                @case('B')
                    @livewire('sections.monthly-stats', ['week' => $week], key('b-'.$week->id))
                    @break
                @case('C')
                    @livewire('sections.production-table', ['week' => $week, 'kind' => 'segment'], key('c-'.$week->id))
                    @break
                @case('D')
                    @livewire('sections.production-table', ['week' => $week, 'kind' => 'ratecode'], key('d-'.$week->id))
                    @break
                @case('EF')
                    @livewire('sections.channel-grid', ['week' => $week], key('ef-'.$week->id))
                    @break
                @case('OWNER')
                    @livewire('sections.owner-overview', ['week' => $week], key('owner-'.$week->id))
                    @break
                @default
                    <x-phase-placeholder
                        phase="Phase 4 — Department Inputs"
                        title="{{ collect($outline)->firstWhere('0', $section)[2] ?? 'Section' }}"
                        description="This written section is filled by its department in Phase 4 (activity rows, notes, screenshots, and AI drafting). The data sections B, C, D, E/F and Owner Overview are editable now." />
            @endswitch
        </div>
    </div>
</x-app-layout>

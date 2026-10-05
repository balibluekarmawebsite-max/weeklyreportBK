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
            <a href="{{ route('reports.index') }}" wire:navigate class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-ink-700 hover:bg-sand-100">← Back</a>
        </div>
    </x-slot>

    <x-phase-placeholder
        phase="Phase 2 — Report Editor"
        title="Report editor"
        description="The section-by-section editor (outline, editable tables, live preview, and AI drafting) is built from Phase 2 onward. This week's record already exists and is ready to be filled." />
</x-app-layout>

<x-app-layout>
    <x-slot name="title">Data Import</x-slot>
    <x-slot name="header">
        <h1 class="font-serif text-2xl font-semibold text-ink-900">Data Import</h1>
        <p class="mt-1 text-sm text-ink-500">Upload VHP exports and apply them to a week.</p>
    </x-slot>

    <x-phase-placeholder
        phase="Phase 3 — Data Import"
        title="VHP file import"
        description="Drag-and-drop upload for VHP Excel/CSV exports, with saved column mappings, validation warnings, and import history. Built in Phase 3; the automatic Playwright robot follows in Phase 8." />
</x-app-layout>

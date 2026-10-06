<x-app-layout>
    <x-slot name="title">Data Import</x-slot>
    <x-slot name="header">
        <h1 class="font-serif text-2xl font-semibold text-ink-900">Data Import</h1>
        <p class="mt-1 text-sm text-ink-500">Import each report section from its own source — CSV, Excel or a screenshot.</p>
    </x-slot>

    {{-- Primary: per-section smart importer --}}
    @livewire('section-import')

    {{-- Secondary: the whole SM-format workbook at once --}}
    <details class="mt-8 group">
        <summary class="cursor-pointer select-none text-sm font-medium text-ink-600 hover:text-ink-800">
            Import a full weekly-report workbook (all sections at once)
        </summary>
        <div class="mt-3">
            @livewire('data-import')
        </div>
    </details>
</x-app-layout>

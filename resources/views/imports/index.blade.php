<x-app-layout>
    <x-slot name="title">Data Import</x-slot>
    <x-slot name="header">
        <h1 class="font-serif text-2xl font-semibold text-ink-900">Data Import</h1>
        <p class="mt-1 text-sm text-ink-500">Upload a weekly-report workbook and apply it to a week.</p>
    </x-slot>

    @livewire('data-import')

    @livewire('vhp-segment-import')
</x-app-layout>

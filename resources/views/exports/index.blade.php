<x-app-layout>
    <x-slot name="title">Export Center</x-slot>
    <x-slot name="header">
        <h1 class="font-serif text-2xl font-semibold text-ink-900">Export Center</h1>
        <p class="mt-1 text-sm text-ink-500">Download any week as Excel, Word or PDF — one shared layout for all three.</p>
    </x-slot>

    <div class="card overflow-hidden">
        @if($weeks->isEmpty())
            <p class="px-6 py-12 text-center text-sm text-ink-500">No weeks for {{ $property?->name }} yet.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                        <th class="px-6 py-3 font-medium">Period</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Last exported</th>
                        <th class="px-6 py-3 font-medium text-right">Download</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach($weeks as $week)
                        <tr class="hover:bg-sand-50">
                            <td class="px-6 py-3 font-medium text-ink-800">{{ $week->label }}</td>
                            <td class="px-6 py-3"><x-status-badge :status="$week->status" /></td>
                            <td class="px-6 py-3 text-ink-500">{{ $week->exported_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-6 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('exports.excel', $week) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                                        <span class="font-mono">XLSX</span> Excel
                                    </a>
                                    <a href="{{ route('exports.word', $week) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-100">
                                        <span class="font-mono">DOCX</span> Word
                                    </a>
                                    <a href="{{ route('exports.pdf', $week) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                        <span class="font-mono">PDF</span> PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <p class="mt-4 text-xs text-ink-400">
        All formats use the same report template: cover, sections A–J and the Owner Overview, with brand colours, IDR / % formatting, bold totals and “–” for blanks. Charts embed in a later update.
    </p>
</x-app-layout>

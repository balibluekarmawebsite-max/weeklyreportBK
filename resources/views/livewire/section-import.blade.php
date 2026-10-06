@php $cls = 'w-full rounded border-sand-300 bg-white px-2 py-1 text-sm focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50'; @endphp
<div class="card mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sand-200 px-5 py-4">
        <div>
            <h2 class="font-serif text-lg font-semibold text-ink-900">Import by section</h2>
            <p class="text-xs text-ink-500">Upload a CSV, Excel, or a screenshot for one section at a time. Only that section is replaced.</p>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-[10px] uppercase tracking-wide text-ink-400">Week</label>
            <select wire:model.live="weekId" class="rounded border-sand-300 bg-white px-2 py-1.5 text-sm focus:border-ink-400 focus:ring-ink-400">
                @forelse($weeks as $w)
                    <option value="{{ $w->id }}">{{ $w->label }}</option>
                @empty
                    <option value="">No weeks yet</option>
                @endforelse
            </select>
        </div>
    </div>

    @if($notice)
        <div class="mx-5 mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 5000)">{{ $notice }}</div>
    @endif

    @if(! $week)
        <p class="px-5 py-8 text-center text-sm text-ink-500">No report week for this property yet. Create one under <a href="{{ route('reports.index') }}" class="text-ink-700 underline">Weekly Reports</a> first.</p>
    @elseif($active === '')
        {{-- Section grid --}}
        @unless($aiReady)
            <p class="mx-5 mt-4 rounded-lg bg-gold-50 px-3 py-2 text-xs text-ink-600">Tip: screenshots and non-standard files are read by AI. Add <code class="rounded bg-sand-100 px-1">GROQ_API_KEY</code> in Settings to enable that. CSV for Section C works without it.</p>
        @endunless
        <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($sections as $key => $s)
                <div class="flex flex-col justify-between rounded-lg border border-sand-200 p-4">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-semibold text-ink-800">{{ $s['label'] }}</h3>
                            @if($filled[$key] ?? false)
                                <span class="badge bg-emerald-100 text-emerald-700">Has data</span>
                            @else
                                <span class="badge bg-slate-100 text-slate-500">Empty</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-ink-400">{{ $s['hint'] }}</p>
                    </div>
                    <button wire:click="openSection('{{ $key }}')" class="mt-3 inline-flex w-max items-center gap-1 rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-xs font-medium text-ink-700 hover:bg-sand-50">
                        {{ ($filled[$key] ?? false) ? 'Replace' : 'Import' }}
                    </button>
                </div>
            @endforeach
        </div>
    @else
        {{-- Per-section uploader --}}
        @php $section = $sections[$active]; $cols = $section['columns']; @endphp
        <div class="px-5 py-5">
            <div class="flex items-center justify-between">
                <h3 class="font-serif text-base font-semibold text-ink-900">{{ $section['label'] }}</h3>
                <button wire:click="close" class="text-sm text-ink-500 hover:text-ink-700">← All sections</button>
            </div>
            <p class="mt-1 text-xs text-ink-500">{{ $section['hint'] }}</p>

            @if($errorMessage)<div class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errorMessage }}</div>@endif

            @unless($previewing)
                <label class="mt-4 block">
                    <span class="text-sm font-medium text-ink-700">Upload file (CSV, Excel or screenshot)</span>
                    <input type="file" wire:model="file" accept=".csv,.txt,.xlsx,.xls,.png,.jpg,.jpeg,.webp"
                        class="mt-2 block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-700 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-ink-600">
                </label>
                @error('file')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                <div wire:loading wire:target="file" class="mt-3 text-sm text-ink-500">Reading{{ $active === 'segment' ? '' : ' with AI' }}…</div>
            @endunless

            @if($previewing)
                @if($aiDraft)
                    <div class="mt-4 rounded-lg bg-gold-50 px-3 py-2 text-sm text-ink-700"><span class="font-medium text-gold-700">AI draft — please verify.</span> The AI read these from your file; check every number against the source before applying.</div>
                @endif
                @if($warnings)
                    <ul class="mt-3 list-disc space-y-1 rounded-lg bg-amber-50 px-6 py-2 text-xs text-amber-800">
                        @foreach($warnings as $w)<li>{{ $w }}</li>@endforeach
                    </ul>
                @endif

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                                @foreach($cols as $field => $c)<th class="px-2 py-2 font-medium">{{ $c['label'] }}</th>@endforeach
                                <th class="px-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sand-100">
                            @foreach($rows as $i => $row)
                                <tr>
                                    @foreach($cols as $field => $c)
                                        <td class="px-2 py-1.5">
                                            <input type="{{ in_array($c['type'], ['int','money','percent']) ? 'number' : 'text' }}" step="any"
                                                class="{{ $cls }} {{ in_array($c['type'], ['int','money','percent']) ? 'text-right tabular-nums' : '' }}"
                                                wire:model="rows.{{ $i }}.{{ $field }}">
                                        </td>
                                    @endforeach
                                    <td class="px-2 py-1.5 text-right"><button wire:click="removeRow({{ $i }})" class="text-ink-300 hover:text-red-500" title="Remove">&times;</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button wire:click="apply" wire:loading.attr="disabled" wire:target="apply"
                        class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                        <span wire:loading.remove wire:target="apply">Apply to this section</span>
                        <span wire:loading wire:target="apply">Applying…</span>
                    </button>
                    <button wire:click="addRow" class="text-sm text-ink-600 hover:text-ink-800">+ Add row</button>
                    <button wire:click="close" class="text-sm text-ink-500 hover:text-ink-700">Cancel</button>
                </div>
            @endif
        </div>
    @endif
</div>

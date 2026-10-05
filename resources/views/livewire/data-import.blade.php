<div>
    {{-- STEP: upload --}}
    @if($step === 'upload')
        <div class="card p-8">
            <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-sand-300 bg-sand-50 px-6 py-12 text-center transition hover:border-ink-300 hover:bg-sand-100">
                <svg class="h-10 w-10 text-ink-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                <span class="mt-3 font-medium text-ink-700">Drop a weekly-report Excel file, or click to choose</span>
                <span class="mt-1 text-xs text-ink-400">.xlsx or .xls · up to 20 MB</span>
                <input type="file" wire:model="file" class="hidden" accept=".xlsx,.xls">
            </label>

            <div wire:loading wire:target="file" class="mt-4 text-center text-sm text-ink-500">Reading &amp; parsing the workbook…</div>
            @error('file') <p class="mt-3 text-center text-sm text-red-600">{{ $message }}</p> @enderror

            <p class="mt-6 text-center text-xs text-ink-400">
                The file's property (BKDS/BKDU/BKV) and week are detected automatically from its name. Errors like <span class="font-mono">#DIV/0!</span> and duplicate rows are cleaned on import.
            </p>
        </div>
    @endif

    {{-- STEP: preview --}}
    @if($step === 'preview' && $parsed)
        @php $meta = $parsed['meta']; @endphp
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
            <div class="card p-6">
                <h2 class="font-serif text-lg font-semibold text-ink-900">Preview</h2>
                <p class="text-sm text-ink-500">{{ $meta['source_filename'] }}</p>

                <dl class="mt-4 grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-ink-400">Detected property</dt>
                    <dd class="font-medium text-ink-800">{{ $meta['property_code'] ?? '— (choose on the right)' }}</dd>
                    <dt class="text-ink-400">Detected period</dt>
                    <dd class="font-medium text-ink-800">{{ $meta['period_label'] ?? '— (set on the right)' }}</dd>
                </dl>

                <h3 class="mt-6 text-xs font-semibold uppercase tracking-wide text-ink-400">Rows found</h3>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach($this->sectionCounts() as $label => $count)
                        <div class="rounded-lg border border-sand-200 bg-sand-50 px-3 py-2">
                            <div class="text-lg font-semibold tabular-nums text-ink-800">{{ $count }}</div>
                            <div class="text-xs text-ink-500">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>

                @if(!empty($parsed['warnings']))
                    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Cleaned on import</div>
                        <ul class="mt-1 list-inside list-disc text-sm text-amber-800">
                            @foreach($parsed['warnings'] as $w)<li>{{ $w }}</li>@endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Apply panel --}}
            <div class="card h-max p-6">
                <h3 class="font-serif text-base font-semibold text-ink-900">Apply to week</h3>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-ink-500">Property</label>
                        <select wire:model="propertyId" class="mt-1 w-full rounded-lg border-sand-300 text-sm focus:border-ink-400 focus:ring-ink-400">
                            <option value="">Choose…</option>
                            @foreach($properties as $p)
                                <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('propertyId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-500">Week (any day — snaps to Friday)</label>
                        <input type="date" wire:model="startDate" class="mt-1 w-full rounded-lg border-sand-300 text-sm focus:border-ink-400 focus:ring-ink-400">
                        @error('startDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <button wire:click="apply" wire:loading.attr="disabled"
                        class="w-full rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                        <span wire:loading.remove wire:target="apply">Apply to week</span>
                        <span wire:loading wire:target="apply">Applying…</span>
                    </button>
                    <button wire:click="reset_" class="w-full text-center text-sm text-ink-500 hover:text-ink-700">Cancel</button>
                    <p class="text-xs text-ink-400">If the week already exists, its data is replaced. Locked weeks are protected.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- STEP: done --}}
    @if($step === 'done')
        <div class="card mx-auto max-w-lg p-8 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            </div>
            <h2 class="mt-4 font-serif text-xl font-semibold text-ink-900">Imported successfully</h2>
            <div class="mx-auto mt-4 max-w-xs space-y-1 text-left text-sm">
                @foreach($summary as $label => $count)
                    <div class="flex justify-between"><span class="text-ink-500">{{ $label }}</span><span class="font-medium tabular-nums text-ink-800">{{ $count }}</span></div>
                @endforeach
            </div>
            <div class="mt-6 flex justify-center gap-3">
                @if($appliedWeekId)
                    <a href="{{ route('reports.show', $appliedWeekId) }}" wire:navigate class="rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600">Open the week →</a>
                @endif
                <button wire:click="reset_" class="rounded-lg border border-sand-300 bg-white px-4 py-2 text-sm font-medium text-ink-700 hover:bg-sand-100">Import another</button>
            </div>
        </div>
    @endif

    {{-- History --}}
    <div class="card mt-8">
        <div class="border-b border-sand-200 px-5 py-3">
            <h2 class="font-serif text-base font-semibold text-ink-900">Import history</h2>
        </div>
        @if($history->isEmpty())
            <p class="px-5 py-6 text-center text-sm text-ink-400">No imports yet.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                        <th class="px-5 py-2 font-medium">File</th>
                        <th class="px-5 py-2 font-medium">Source</th>
                        <th class="px-5 py-2 font-medium">Property</th>
                        <th class="px-5 py-2 font-medium">Period</th>
                        <th class="px-5 py-2 font-medium">Status</th>
                        <th class="px-5 py-2 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach($history as $imp)
                        <tr>
                            <td class="px-5 py-2 text-ink-700">{{ \Illuminate\Support\Str::limit($imp->original_filename, 40) }}</td>
                            <td class="px-5 py-2">
                                @if($imp->source === 'vhp')
                                    <span class="badge bg-gold-100 text-gold-600">VHP robot</span>
                                @else
                                    <span class="badge bg-slate-100 text-slate-500">Manual</span>
                                @endif
                            </td>
                            <td class="px-5 py-2 text-ink-600">{{ $imp->property?->code ?? '–' }}</td>
                            <td class="px-5 py-2 text-ink-600">{{ $imp->period_label ?? '–' }}</td>
                            <td class="px-5 py-2">
                                @php $badge = [
                                    'applied' => 'bg-emerald-100 text-emerald-700',
                                    'parsed' => 'bg-sky-100 text-sky-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                    'pending' => 'bg-slate-100 text-slate-600',
                                ][$imp->status] ?? 'bg-slate-100 text-slate-600'; @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst($imp->status) }}</span>
                            </td>
                            <td class="px-5 py-2 text-ink-500">{{ $imp->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

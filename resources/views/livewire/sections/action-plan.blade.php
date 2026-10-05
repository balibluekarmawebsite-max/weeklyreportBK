@php $readonly = $week->isLocked();
   $cls = 'w-full rounded border-sand-300 bg-white px-2 py-1 text-sm focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50'; @endphp
<div>
    <div class="card">
        <div class="flex items-center justify-between border-b border-sand-200 px-5 py-4">
            <div>
                <h2 class="font-serif text-lg font-semibold text-ink-900">J · Next Week Action Plan</h2>
                <p class="text-xs text-ink-500">Group by category (e.g. Offline Agent, Room Promotion, Marketing).</p>
            </div>
            @unless($readonly)
                <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving…</span>
                </button>
            @endunless
        </div>
        @if($saved)<div class="mx-5 mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 2500)">Saved.</div>@endif
        @if($aiError)<div class="mx-5 mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $aiError }}</div>@endif

        <div class="divide-y divide-sand-100">
            @foreach($rows as $i => $row)
                <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-[160px_1fr]">
                    <div class="space-y-2">
                        <div><label class="text-[10px] uppercase tracking-wide text-ink-400">Category</label>
                            <input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.category" placeholder="e.g. Marketing"></div>
                        <div><label class="text-[10px] uppercase tracking-wide text-ink-400">Plan</label>
                            <input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.plan" placeholder="Plan"></div>
                        <div class="flex gap-2">
                            <div class="flex-1"><label class="text-[10px] uppercase tracking-wide text-ink-400">Start</label>
                                <input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.start_label"></div>
                            <div class="flex-1"><label class="text-[10px] uppercase tracking-wide text-ink-400">Deadline</label>
                                <input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.deadline_label"></div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1"><label class="text-[10px] uppercase tracking-wide text-ink-400">Remark</label>
                            <textarea {{ $readonly?'disabled':'' }} rows="5" class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.remark" placeholder="Details…"></textarea>
                            @if($aiReady && ! $readonly)
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs" wire:loading.class="opacity-50" wire:target="aiRewrite({{ $i }}, 'remark', 'rewrite'),aiRewrite({{ $i }}, 'remark', 'shorten'),aiRewrite({{ $i }}, 'remark', 'translate_id'),aiRewrite({{ $i }}, 'remark', 'translate_en')">
                                    <span class="text-gold-600">✨</span>
                                    <button wire:click="aiRewrite({{ $i }}, 'remark', 'rewrite')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'remark', 'rewrite')" class="rounded px-1.5 py-0.5 text-ink-600 hover:bg-sand-100 disabled:opacity-60">Rewrite</button>
                                    <button wire:click="aiRewrite({{ $i }}, 'remark', 'shorten')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'remark', 'shorten')" class="rounded px-1.5 py-0.5 text-ink-600 hover:bg-sand-100 disabled:opacity-60">Shorten</button>
                                    <button wire:click="aiRewrite({{ $i }}, 'remark', 'translate_id')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'remark', 'translate_id')" class="rounded px-1.5 py-0.5 text-ink-600 hover:bg-sand-100 disabled:opacity-60" title="Translate to Indonesian">→ ID</button>
                                    <button wire:click="aiRewrite({{ $i }}, 'remark', 'translate_en')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'remark', 'translate_en')" class="rounded px-1.5 py-0.5 text-ink-600 hover:bg-sand-100 disabled:opacity-60" title="Translate to English">→ EN</button>
                                </div>
                            @endif
                        </div>
                        @unless($readonly)<div class="pt-5"><button wire:click="removeRow({{ $i }})" class="text-ink-300 hover:text-red-500">&times;</button></div>@endunless
                    </div>
                </div>
            @endforeach
        </div>
        @unless($readonly)<div class="border-t border-sand-100 px-4 py-3"><button wire:click="addRow" class="text-sm font-medium text-ink-600 hover:text-ink-800">+ Add plan</button></div>@endunless
    </div>
</div>

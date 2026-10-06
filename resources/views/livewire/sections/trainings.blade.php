@php $readonly = $week->isLocked();
   $cls = 'w-full rounded border-sand-300 bg-white px-2 py-1 text-sm focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50'; @endphp
<div>
    <div class="card">
        <div class="flex items-center justify-between border-b border-sand-200 px-5 py-4">
            <h2 class="font-serif text-lg font-semibold text-ink-900">I · Training</h2>
            @unless($readonly)
                <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving…</span>
                </button>
            @endunless
        </div>
        @if($saved)<div class="mx-5 mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 2500)">Saved.</div>@endif
        @if($aiError)<div class="mx-5 mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $aiError }}</div>@endif

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                    <th class="px-4 py-2 font-medium">Date</th>
                    <th class="px-4 py-2 font-medium">Topic</th>
                    <th class="px-4 py-2 font-medium">Duration</th>
                    <th class="px-4 py-2 font-medium">Trainer</th>
                    <th class="px-4 py-2 font-medium">Participants</th>
                    <th class="px-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @foreach($rows as $i => $row)
                    <tr>
                        <td class="px-4 py-1.5 w-32"><input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.date_label"></td>
                        <td class="px-4 py-1.5">
                            <input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.topic">
                            @if($aiReady && ! $readonly)
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs" wire:loading.class="opacity-50" wire:target="aiRewrite({{ $i }}, 'rewrite'),aiRewrite({{ $i }}, 'translate_en'),aiRewrite({{ $i }}, 'translate_id')">
                                    <span class="text-gold-600">✨</span>
                                    <button wire:click="aiRewrite({{ $i }}, 'rewrite')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'rewrite')" class="rounded px-1.5 py-0.5 text-ink-600 hover:bg-sand-100 disabled:opacity-60">Rewrite</button>
                                    <button wire:click="aiRewrite({{ $i }}, 'translate_en')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'translate_en')" class="rounded px-1.5 py-0.5 text-ink-600 hover:bg-sand-100 disabled:opacity-60" title="Translate to English">→ EN</button>
                                    <button wire:click="aiRewrite({{ $i }}, 'translate_id')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'translate_id')" class="rounded px-1.5 py-0.5 text-ink-600 hover:bg-sand-100 disabled:opacity-60" title="Translate to Indonesian">→ ID</button>
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-1.5 w-24"><input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.duration"></td>
                        <td class="px-4 py-1.5 w-32"><input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.trainer"></td>
                        <td class="px-4 py-1.5"><input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.participants"></td>
                        <td class="px-2 py-1.5 text-right">@unless($readonly)<button wire:click="removeRow({{ $i }})" class="text-ink-300 hover:text-red-500">&times;</button>@endunless</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @unless($readonly)<div class="border-t border-sand-100 px-4 py-3"><button wire:click="addRow" class="text-sm font-medium text-ink-600 hover:text-ink-800">+ Add training</button></div>@endunless
    </div>
</div>

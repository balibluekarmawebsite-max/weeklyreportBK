@php $readonly = $week->isLocked();
   $cls = 'w-full rounded border-sand-300 bg-white px-2 py-1 text-sm focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50'; @endphp
<div>
    <div class="card">
        <div class="flex items-center justify-between border-b border-sand-200 px-5 py-4">
            <div>
                <h2 class="font-serif text-lg font-semibold text-ink-900">{{ $this->heading() }}</h2>
                <p class="text-xs text-ink-500">Add each activity with its date and notes.</p>
            </div>
            @unless($readonly)
                <button wire:click="save" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            @endunless
        </div>

        @if($saved)
            <div class="mx-5 mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 2500)">Saved.</div>
        @endif

        <div class="divide-y divide-sand-100">
            @foreach($rows as $i => $row)
                <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-[150px_1fr_auto]">
                    <div>
                        <label class="text-[10px] uppercase tracking-wide text-ink-400">Date</label>
                        <input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.date_label" placeholder="25 Sep 2026">
                        <label class="mt-2 block text-[10px] uppercase tracking-wide text-ink-400">{{ $this->titleLabel() }}</label>
                        <input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.title" placeholder="{{ $this->titleLabel() }}">
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wide text-ink-400">Notes / remarks</label>
                        <textarea {{ $readonly?'disabled':'' }} rows="3" class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.notes" placeholder="Details…"></textarea>
                    </div>
                    <div class="flex items-start pt-5">
                        @unless($readonly)
                            <button wire:click="removeRow({{ $i }})" class="text-ink-300 hover:text-red-500" title="Remove">&times;</button>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>

        @unless($readonly)
            <div class="border-t border-sand-100 px-4 py-3"><button wire:click="addRow" class="text-sm font-medium text-ink-600 hover:text-ink-800">+ Add activity</button></div>
        @endunless
    </div>
</div>

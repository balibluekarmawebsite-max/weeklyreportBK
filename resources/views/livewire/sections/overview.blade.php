@php $readonly = $week->isLocked(); @endphp
<div>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="font-serif text-lg font-semibold text-ink-900">A · Sales &amp; Marketing Overview</h2>
            <p class="text-xs text-ink-500">Written commentary. AI drafting from the numbers arrives in Phase 7.</p>
        </div>
        @unless($readonly)
            <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving…</span>
            </button>
        @endunless
    </div>

    @if($saved)<div class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 2500)">Saved.</div>@endif

    <div class="space-y-4">
        @foreach($rows as $i => $row)
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="font-serif text-base font-semibold text-ink-900">{{ $row['heading'] }}</h3>
                    @if($row['ai_draft'])<span class="badge bg-gold-100 text-gold-600">AI draft</span>@endif
                </div>
                <textarea {{ $readonly?'disabled':'' }} rows="4" class="mt-3 w-full rounded-lg border-sand-300 bg-white px-3 py-2 text-sm leading-relaxed focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50"
                    wire:model.blur="rows.{{ $i }}.body" placeholder="Write the {{ strtolower($row['heading']) }} commentary…"></textarea>
            </div>
        @endforeach
    </div>
</div>

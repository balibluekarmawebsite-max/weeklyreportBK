@php $readonly = $week->isLocked(); @endphp
<div>
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h2 class="font-serif text-lg font-semibold text-ink-900">A · Sales &amp; Marketing Overview</h2>
            <p class="text-xs text-ink-500">Written commentary. Use <span class="text-gold-600">✨ AI</span> to draft from the week's figures, then edit and save.</p>
        </div>
        @unless($readonly)
            <button wire:click="save" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving…</span>
            </button>
        @endunless
    </div>

    {{-- AI status + anomaly scan toolbar --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <button wire:click="checkAnomalies" wire:loading.attr="disabled" wire:target="checkAnomalies"
            class="inline-flex items-center gap-1.5 rounded-lg border border-sand-300 bg-white px-3 py-1.5 text-xs font-medium text-ink-700 hover:bg-sand-50">
            <span wire:loading.remove wire:target="checkAnomalies">Check anomalies</span>
            <span wire:loading wire:target="checkAnomalies">Checking…</span>
        </button>
        @unless($aiReady)
            <span class="text-xs text-ink-400">AI drafting is off — add <code class="rounded bg-sand-100 px-1">GROQ_API_KEY</code> to your server <code class="rounded bg-sand-100 px-1">.env</code> to enable ✨.</span>
        @endunless
    </div>

    @if($aiError)
        <div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $aiError }}</div>
    @endif

    @if($anomaliesChecked)
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ empty($anomalies) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800' }}"
            x-data x-init="$nextTick(() => $el.scrollIntoView({block:'nearest'}))">
            @if(empty($anomalies))
                No anomalies found — figures look consistent with budget and all key sections have data.
            @else
                <p class="font-medium">{{ count($anomalies) }} thing(s) to check:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($anomalies as $a)<li>{{ $a }}</li>@endforeach
                </ul>
            @endif
        </div>
    @endif

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

                @if($aiReady && ! $readonly)
                    <div class="mt-2 flex flex-wrap items-center gap-2" wire:loading.class="opacity-50" wire:target="draftWithAi,aiRewrite">
                        <button wire:click="draftWithAi({{ $i }})" wire:loading.attr="disabled" wire:target="draftWithAi({{ $i }})"
                            class="inline-flex items-center gap-1 rounded-md border border-gold-300 bg-gold-50 px-2.5 py-1 text-xs font-medium text-gold-700 hover:bg-gold-100 disabled:opacity-60">
                            <span wire:loading.remove wire:target="draftWithAi({{ $i }})">✨ Draft with AI</span>
                            <span wire:loading wire:target="draftWithAi({{ $i }})">Drafting…</span>
                        </button>
                        <span class="text-ink-200">|</span>
                        <button wire:click="aiRewrite({{ $i }}, 'rewrite')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'rewrite')"
                            class="rounded-md px-2 py-1 text-xs text-ink-600 hover:bg-sand-100 disabled:opacity-60">Rewrite</button>
                        <button wire:click="aiRewrite({{ $i }}, 'shorten')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'shorten')"
                            class="rounded-md px-2 py-1 text-xs text-ink-600 hover:bg-sand-100 disabled:opacity-60">Shorten</button>
                        <button wire:click="aiRewrite({{ $i }}, 'translate_id')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'translate_id')"
                            class="rounded-md px-2 py-1 text-xs text-ink-600 hover:bg-sand-100 disabled:opacity-60" title="Translate to Indonesian">→ ID</button>
                        <button wire:click="aiRewrite({{ $i }}, 'translate_en')" wire:loading.attr="disabled" wire:target="aiRewrite({{ $i }}, 'translate_en')"
                            class="rounded-md px-2 py-1 text-xs text-ink-600 hover:bg-sand-100 disabled:opacity-60" title="Translate to English">→ EN</button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>

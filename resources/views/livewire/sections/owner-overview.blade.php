@php use App\Support\Format; $readonly = $week->isLocked();
   $cls = 'w-full rounded border-sand-300 bg-white px-2 py-1 text-sm focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50';
   $num = $cls.' text-right tabular-nums'; @endphp
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="font-serif text-lg font-semibold text-ink-900">Owner Overview</h2>
        @unless($readonly)
            <button wire:click="save" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        @endunless
    </div>

    @if($saved)
        <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 2500)">Saved.</div>
    @endif

    {{-- Repeater guest performance --}}
    <div class="card">
        <div class="border-b border-sand-200 px-5 py-3">
            <h3 class="font-serif text-base font-semibold text-ink-900">1 · Repeater Guest — Room Performance</h3>
            <p class="text-xs text-ink-500">ADR is calculated from revenue ÷ room nights.</p>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                    <th class="px-4 py-2 font-medium">Month</th>
                    <th class="px-4 py-2 font-medium text-right">Room Nights</th>
                    <th class="px-4 py-2 font-medium text-right">ADR</th>
                    <th class="px-4 py-2 font-medium text-right">Revenue</th>
                    <th class="px-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @foreach($repeater as $i => $row)
                    <tr>
                        <td class="px-4 py-1.5"><input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="repeater.{{ $i }}.label" placeholder="e.g. September 2026"></td>
                        <td class="px-4 py-1.5 w-28"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $num }}" wire:model.blur="repeater.{{ $i }}.room_nights"></td>
                        <td class="px-4 py-1.5 text-right tabular-nums text-ink-600">{{ Format::idr($this->adr($row)) }}</td>
                        <td class="px-4 py-1.5 w-40"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $num }}" wire:model.blur="repeater.{{ $i }}.revenue"></td>
                        <td class="px-2 py-1.5 text-right">
                            @unless($readonly)<button wire:click="removeRepeater({{ $i }})" class="text-ink-300 hover:text-red-500">&times;</button>@endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-ink-200 font-semibold text-ink-800">
                    <td class="px-4 py-2">Total</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ Format::number($this->repeaterTotals['rn']) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ Format::idr($this->repeaterTotals['adr']) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ Format::idr($this->repeaterTotals['rev']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        @unless($readonly)
            <div class="border-t border-sand-100 px-4 py-3"><button wire:click="addRepeater" class="text-sm font-medium text-ink-600 hover:text-ink-800">+ Add month</button></div>
        @endunless
    </div>

    {{-- Channel mix --}}
    <div class="card">
        <div class="border-b border-sand-200 px-5 py-3">
            <h3 class="font-serif text-base font-semibold text-ink-900">2 · Channel Mix (Market Segmentation)</h3>
            <p class="text-xs text-ink-500">ARR and % share are calculated.</p>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                    <th class="px-4 py-2 font-medium">Source</th>
                    <th class="px-4 py-2 font-medium text-right">RN Sold</th>
                    <th class="px-4 py-2 font-medium text-right">ARR</th>
                    <th class="px-4 py-2 font-medium text-right">Revenue</th>
                    <th class="px-4 py-2 font-medium text-right">%</th>
                    <th class="px-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @foreach($mix as $i => $row)
                    <tr>
                        <td class="px-4 py-1.5"><input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="mix.{{ $i }}.label" placeholder="e.g. OTA"></td>
                        <td class="px-4 py-1.5 w-28"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $num }}" wire:model.blur="mix.{{ $i }}.rn_sold"></td>
                        <td class="px-4 py-1.5 text-right tabular-nums text-ink-600">{{ Format::idr($this->mixArr($row)) }}</td>
                        <td class="px-4 py-1.5 w-40"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $num }}" wire:model.blur="mix.{{ $i }}.gross_revenue"></td>
                        <td class="px-4 py-1.5 text-right tabular-nums text-ink-600">{{ Format::percent($this->mixShare($row)) }}</td>
                        <td class="px-2 py-1.5 text-right">
                            @unless($readonly)<button wire:click="removeMix({{ $i }})" class="text-ink-300 hover:text-red-500">&times;</button>@endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-ink-200 font-semibold text-ink-800">
                    <td class="px-4 py-2">Total</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ Format::number($this->mixTotals['rn']) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ Format::idr($this->mixTotals['arr']) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ Format::idr($this->mixTotals['revenue']) }}</td>
                    <td class="px-4 py-2 text-right">100%</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        @unless($readonly)
            <div class="border-t border-sand-100 px-4 py-3"><button wire:click="addMix" class="text-sm font-medium text-ink-600 hover:text-ink-800">+ Add source</button></div>
        @endunless
    </div>
</div>

@php use App\Support\Format; use App\Models\ChannelMonthRn; $readonly = $week->isLocked(); @endphp
<div>
    <div class="card">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sand-200 px-5 py-4">
            <div>
                <h2 class="font-serif text-lg font-semibold text-ink-900">E / F · Channel Inside (Room Nights)</h2>
                <p class="text-xs text-ink-500">Room nights by source and month. YTD and % are calculated.</p>
            </div>
            <div class="flex items-center gap-3">
                <label class="text-xs text-ink-400">Year</label>
                <select wire:model.live="year" class="rounded-lg border-sand-300 bg-white py-1.5 pl-3 pr-8 text-sm focus:border-ink-400 focus:ring-ink-400">
                    @foreach($years as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
                @unless($readonly)
                    <button wire:click="save" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                @endunless
            </div>
        </div>

        @if($saved)
            <div class="mx-5 mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 2500)">Saved.</div>
        @endif

        @php $cell = 'w-12 rounded border-sand-300 bg-white px-1 py-1 text-right text-xs tabular-nums focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50'; @endphp
        <div class="overflow-x-auto p-2">
            <table class="w-full border-separate border-spacing-0 text-xs">
                <thead>
                    <tr class="text-[10px] uppercase text-ink-400">
                        <th class="sticky left-0 bg-white px-2 py-2 text-left">Source</th>
                        @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $mn)
                            <th class="px-1 py-2">{{ $mn }}</th>
                        @endforeach
                        <th class="px-2 py-2 text-right">YTD</th>
                        <th class="px-2 py-2 text-right">%</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                        @php $ytd = $this->rowYtd($row); @endphp
                        <tr class="border-t border-sand-100">
                            <td class="sticky left-0 bg-white px-1 py-1"><input {{ $readonly?'disabled':'' }} class="w-36 rounded border-sand-300 bg-white px-2 py-1 text-xs focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50" wire:model.blur="rows.{{ $i }}.source_label"></td>
                            @foreach(ChannelMonthRn::MONTHS as $m)
                                <td class="px-0.5 py-1"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $cell }}" wire:model.blur="rows.{{ $i }}.{{ $m }}"></td>
                            @endforeach
                            <td class="px-2 py-1 text-right font-medium tabular-nums">{{ Format::number($ytd) }}</td>
                            <td class="px-2 py-1 text-right tabular-nums text-ink-500">{{ Format::percent(\App\Support\ReportCalculator::sharePercent($ytd, $this->grandYtd)) }}</td>
                            <td class="px-1 text-right">
                                @unless($readonly)
                                    <button wire:click="removeRow({{ $i }})" class="text-ink-300 hover:text-red-500" title="Remove">&times;</button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-ink-200 font-semibold text-ink-800">
                        <td class="sticky left-0 bg-white px-2 py-2 text-left">Total</td>
                        <td colspan="12"></td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ Format::number($this->grandYtd) }}</td>
                        <td class="px-2 py-2 text-right">100%</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @unless($readonly)
            <div class="border-t border-sand-100 px-4 py-3">
                <button wire:click="addRow" class="text-sm font-medium text-ink-600 hover:text-ink-800">+ Add source</button>
            </div>
        @endunless
    </div>
</div>

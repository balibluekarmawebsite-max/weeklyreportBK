@php use App\Support\Format; @endphp
<div>
    <div class="card">
        <div class="flex items-center justify-between border-b border-sand-200 px-5 py-4">
            <div>
                <h2 class="font-serif text-lg font-semibold text-ink-900">B · Year to Date Actual &amp; On-Hand Forecast</h2>
                <p class="text-xs text-ink-500">Actual vs Budget vs Last Year. Occupancy entered as a percentage.</p>
            </div>
            @unless($week->isLocked())
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

        <div class="overflow-x-auto p-2">
            @php $readonly = $week->isLocked(); $cls = 'w-20 rounded border-sand-300 bg-white px-1.5 py-1 text-right text-xs tabular-nums focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50'; @endphp
            <table class="w-full border-separate border-spacing-0 text-xs">
                <thead>
                    <tr class="text-ink-400">
                        <th class="sticky left-0 bg-white px-2 py-2 text-left">Month</th>
                        <th class="px-2 py-2">RN Sold</th>
                        <th class="px-2 py-2 text-center" colspan="3">Occupancy %</th>
                        <th class="px-2 py-2 text-center" colspan="3">ARR (IDR)</th>
                        <th class="px-2 py-2 text-center" colspan="3">Revenue (IDR)</th>
                    </tr>
                    <tr class="text-[10px] uppercase text-ink-300">
                        <th class="sticky left-0 bg-white"></th>
                        <th></th>
                        <th class="px-1">Act</th><th class="px-1">Bud</th><th class="px-1">LY</th>
                        <th class="px-1">Act</th><th class="px-1">Bud</th><th class="px-1">LY</th>
                        <th class="px-1">Act</th><th class="px-1">Bud</th><th class="px-1">LY</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $m => $row)
                        @php $var = Format::variance((float)($row['occ_actual'] ?: 0), (float)($row['occ_budget'] ?: 0)); @endphp
                        <tr class="border-t border-sand-100">
                            <td class="sticky left-0 bg-white px-2 py-1 text-left font-medium text-ink-700">{{ \Illuminate\Support\Str::substr($row['name'],0,3) }}</td>
                            <td class="px-1 py-1"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $cls }} w-16" wire:model.blur="rows.{{ $m }}.rn_sold"></td>
                            <td class="px-1 py-1"><input type="number" step="0.01" {{ $readonly?'disabled':'' }} class="{{ $cls }} w-16" wire:model.blur="rows.{{ $m }}.occ_actual"></td>
                            <td class="px-1 py-1"><input type="number" step="0.01" {{ $readonly?'disabled':'' }} class="{{ $cls }} w-16" wire:model.blur="rows.{{ $m }}.occ_budget"></td>
                            <td class="px-1 py-1"><input type="number" step="0.01" {{ $readonly?'disabled':'' }} class="{{ $cls }} w-16" wire:model.blur="rows.{{ $m }}.occ_ly"></td>
                            <td class="px-1 py-1"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $m }}.arr_actual"></td>
                            <td class="px-1 py-1"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $m }}.arr_budget"></td>
                            <td class="px-1 py-1"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $m }}.arr_ly"></td>
                            <td class="px-1 py-1"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $cls }} w-28" wire:model.blur="rows.{{ $m }}.rev_actual"></td>
                            <td class="px-1 py-1"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $cls }} w-28" wire:model.blur="rows.{{ $m }}.rev_budget"></td>
                            <td class="px-1 py-1"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $cls }} w-28" wire:model.blur="rows.{{ $m }}.rev_ly"></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-ink-200 font-semibold text-ink-800">
                        <td class="sticky left-0 bg-white px-2 py-2 text-left">Total</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ Format::number($this->totals['rn']) }}</td>
                        <td colspan="3"></td>
                        <td colspan="3"></td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ Format::idr($this->totals['revA']) }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ Format::idr($this->totals['revB']) }}</td>
                        <td class="px-2 py-2 text-right tabular-nums">{{ Format::idr($this->totals['revL']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p class="mt-3 text-xs text-ink-400">Tip: enter occupancy as a percentage (e.g. <span class="font-mono">85.3</span>). Blank cells show as “–” in the report, never as errors.</p>
</div>

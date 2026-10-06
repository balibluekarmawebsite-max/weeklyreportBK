@php use App\Support\Format; $readonly = $week->isLocked(); @endphp
<div>
    <div class="card">
        <div class="flex items-center justify-between border-b border-sand-200 px-5 py-4">
            <div>
                <h2 class="font-serif text-lg font-semibold text-ink-900">{{ $this->title() }}</h2>
                <p class="text-xs text-ink-500">ARR and % share are calculated automatically.</p>
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

        @php $cls = 'w-full rounded border-sand-300 bg-white px-2 py-1 text-sm focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50'; $num = $cls.' text-right tabular-nums'; @endphp
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                    @if($kind === 'segment')<th class="px-4 py-2 font-medium">Category</th>@endif
                    <th class="px-4 py-2 font-medium">{{ $this->labelHeading() }}</th>
                    <th class="px-4 py-2 font-medium text-right">RN Sold</th>
                    <th class="px-4 py-2 font-medium text-right">Gross Revenue</th>
                    <th class="px-4 py-2 font-medium text-right">ARR</th>
                    <th class="px-4 py-2 font-medium text-right">%</th>
                    <th class="px-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @foreach($rows as $i => $row)
                    <tr>
                        @if($kind === 'segment')<td class="px-4 py-1.5 w-40"><input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.group" placeholder="e.g. OTA"></td>@endif
                        <td class="px-4 py-1.5"><input {{ $readonly?'disabled':'' }} class="{{ $cls }}" wire:model.blur="rows.{{ $i }}.label" placeholder="e.g. Booking.com"></td>
                        <td class="px-4 py-1.5 w-28"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $num }}" wire:model.blur="rows.{{ $i }}.rn_sold"></td>
                        <td class="px-4 py-1.5 w-40"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $num }}" wire:model.blur="rows.{{ $i }}.gross_revenue"></td>
                        <td class="px-4 py-1.5 text-right tabular-nums text-ink-600">{{ Format::idr($this->arr($row)) }}</td>
                        <td class="px-4 py-1.5 text-right tabular-nums text-ink-600">{{ Format::percent($this->share($row)) }}</td>
                        <td class="px-2 py-1.5 text-right">
                            @unless($readonly)
                                <button wire:click="removeRow({{ $i }})" class="text-ink-300 hover:text-red-500" title="Remove row">&times;</button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-ink-200 font-semibold text-ink-800">
                    <td class="px-4 py-2" @if($kind === 'segment') colspan="2" @endif>Total</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ Format::number($this->totals['rn']) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ Format::idr($this->totals['revenue']) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ Format::idr($this->totals['arr']) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">100%</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        @unless($readonly)
            <div class="border-t border-sand-100 px-4 py-3">
                <button wire:click="addRow" class="text-sm font-medium text-ink-600 hover:text-ink-800">+ Add row</button>
            </div>
        @endunless
    </div>
</div>

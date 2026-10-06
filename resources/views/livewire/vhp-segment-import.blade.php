@php use App\Support\Format; use App\Support\ReportCalculator; @endphp
<div class="card mt-6">
    <div class="border-b border-sand-200 px-5 py-4">
        <h2 class="font-serif text-lg font-semibold text-ink-900">VHP CSV → Section C (Market Segment)</h2>
        <p class="text-xs text-ink-500">Upload VHP's <em>Reservation By Creation Date</em> CSV. It's grouped by VHP's Segment column; only Section C is updated.</p>
    </div>

    @if($errorMessage)
        <div class="mx-5 mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    {{-- Upload --}}
    @if($step === 'upload')
        <div class="px-5 py-6">
            <label class="block">
                <span class="text-sm font-medium text-ink-700">Reservation By Creation Date (.csv)</span>
                <input type="file" wire:model="file" accept=".csv,text/csv"
                    class="mt-2 block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-700 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-ink-600">
            </label>
            @error('file')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <div wire:loading wire:target="file" class="mt-3 text-sm text-ink-500">Reading…</div>
            <p class="mt-3 text-xs text-ink-400">Set the date range to the report week and export from VHP first. Cancelled bookings are excluded; complimentary is kept as its own group.</p>
        </div>
    @endif

    {{-- Preview --}}
    @if($step === 'preview' && $parsed)
        <div class="px-5 py-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-[10px] uppercase tracking-wide text-ink-400">Property {{ $propertyName ? "(detected: {$propertyName})" : '' }}</label>
                    <select wire:model="propertyId" class="mt-1 w-full rounded border-sand-300 bg-white px-2 py-1.5 text-sm focus:border-ink-400 focus:ring-ink-400">
                        <option value="">— choose —</option>
                        @foreach($properties as $p)<option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>@endforeach
                    </select>
                    @error('propertyId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wide text-ink-400">Week start {{ $periodLabel ? "(period: {$periodLabel})" : '' }}</label>
                    <input type="date" wire:model="startDate" class="mt-1 w-full rounded border-sand-300 bg-white px-2 py-1.5 text-sm focus:border-ink-400 focus:ring-ink-400">
                    @error('startDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            @if($warnings)
                <ul class="mt-4 list-disc space-y-1 rounded-lg bg-amber-50 px-6 py-3 text-xs text-amber-800">
                    @foreach($warnings as $w)<li>{{ $w }}</li>@endforeach
                </ul>
            @endif

            @php $grandRn = 0; $grandRev = 0; @endphp
            <table class="mt-4 w-full text-sm">
                <thead>
                    <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                        <th class="px-3 py-2 font-medium">Category / Agent</th>
                        <th class="px-3 py-2 font-medium text-right">RN Sold</th>
                        <th class="px-3 py-2 font-medium text-right">ADR</th>
                        <th class="px-3 py-2 font-medium text-right">Gross Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->grouped() as $group => $g)
                        <tr class="bg-sand-50"><td class="px-3 py-1.5 font-semibold text-ink-800" colspan="4">{{ $group }}</td></tr>
                        @foreach($g['rows'] as $row)
                            <tr class="border-b border-sand-100">
                                <td class="px-3 py-1.5 pl-6 text-ink-700">{{ $row['label'] }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ Format::number($row['rn_sold']) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-ink-600">{{ Format::idr(ReportCalculator::rate($row['gross_revenue'] ?: null, $row['rn_sold'] ?: null)) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ Format::idr($row['gross_revenue']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="text-xs text-ink-500">
                            <td class="px-3 py-1 pl-6">{{ $group }} subtotal</td>
                            <td class="px-3 py-1 text-right tabular-nums">{{ Format::number($g['rn']) }}</td>
                            <td></td>
                            <td class="px-3 py-1 text-right tabular-nums">{{ Format::idr($g['revenue']) }}</td>
                        </tr>
                        @php $grandRn += $g['rn']; $grandRev += $g['revenue']; @endphp
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-ink-200 font-semibold text-ink-800">
                        <td class="px-3 py-2">Total</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ Format::number($grandRn) }}</td>
                        <td></td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ Format::idr($grandRev) }}</td>
                    </tr>
                </tfoot>
            </table>

            <div class="mt-5 flex items-center gap-3">
                <button wire:click="apply" wire:loading.attr="disabled" wire:target="apply"
                    class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600 disabled:opacity-60">
                    <span wire:loading.remove wire:target="apply">Apply to Section C</span>
                    <span wire:loading wire:target="apply">Applying…</span>
                </button>
                <button wire:click="resetForm" class="text-sm text-ink-500 hover:text-ink-700">Cancel</button>
            </div>
        </div>
    @endif

    {{-- Done --}}
    @if($step === 'done')
        <div class="px-5 py-6">
            <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                Section C updated from the VHP CSV. The other sections of this week were not changed.
            </div>
            <div class="mt-4 flex gap-3">
                <a href="{{ route('reports.show', $appliedWeekId) }}" class="inline-flex items-center rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600">Open the report</a>
                <button wire:click="resetForm" class="text-sm text-ink-500 hover:text-ink-700">Import another</button>
            </div>
        </div>
    @endif
</div>

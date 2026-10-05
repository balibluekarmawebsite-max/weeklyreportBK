@php use App\Support\Format; $readonly = $week->isLocked();
   $num = 'w-28 rounded border-sand-300 bg-white px-2 py-1 text-right text-sm tabular-nums focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50'; @endphp
<div>
    <div class="card">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sand-200 px-5 py-4">
            <div>
                <h2 class="font-serif text-lg font-semibold text-ink-900">H · Social Media Insight</h2>
                <p class="text-xs text-ink-500">Last 7 days vs previous week. Growth is calculated.</p>
            </div>
            <div class="flex items-center gap-3">
                <input {{ $readonly?'disabled':'' }} class="w-36 rounded border-sand-300 bg-white px-2 py-1 text-sm focus:border-ink-400 focus:ring-ink-400 disabled:bg-sand-50" wire:model.blur="platform" placeholder="Platform">
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

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                    <th class="px-5 py-2 font-medium">Metric</th>
                    <th class="px-5 py-2 font-medium text-right">Last Week</th>
                    <th class="px-5 py-2 font-medium text-right">This Week</th>
                    <th class="px-5 py-2 font-medium text-right">Growth</th>
                    <th class="px-5 py-2 font-medium text-right">Growth %</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @foreach($rows as $i => $row)
                    @php $g = $this->growth($row); $gp = $this->growthPercent($row);
                         $gc = $g === null ? 'text-ink-400' : ($g >= 0 ? 'text-emerald-600' : 'text-red-600'); @endphp
                    <tr>
                        <td class="px-5 py-2 font-medium text-ink-700">{{ $row['label'] }}</td>
                        <td class="px-5 py-1.5 text-right"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $num }}" wire:model.blur="rows.{{ $i }}.last_week"></td>
                        <td class="px-5 py-1.5 text-right"><input type="number" {{ $readonly?'disabled':'' }} class="{{ $num }}" wire:model.blur="rows.{{ $i }}.this_week"></td>
                        <td class="px-5 py-2 text-right tabular-nums {{ $gc }}">{{ $g === null ? '–' : ($g >= 0 ? '+' : '').Format::number($g) }}</td>
                        <td class="px-5 py-2 text-right tabular-nums {{ $gc }}">{{ $gp === null ? '–' : ($gp >= 0 ? '+' : '').number_format($gp, 2).'%' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@props([
    'label' => '',
    'value' => '–',
    'sub' => null,
    'variance' => null,      // ['pct' => float|null, 'direction' => 'up'|'down'|'flat']
    'invertColor' => false,  // for metrics where "down" is good (e.g. cancellations)
])

@php
    $dir = $variance['direction'] ?? 'flat';
    $good = $invertColor ? 'down' : 'up';
    $bad = $invertColor ? 'up' : 'down';
    $vClass = $dir === $good ? 'text-emerald-600' : ($dir === $bad ? 'text-red-600' : 'text-ink-400');
    $arrow = $dir === 'up' ? '▲' : ($dir === 'down' ? '▼' : '–');
@endphp

<div class="card p-5">
    <div class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $label }}</div>
    <div class="mt-2 font-serif text-2xl font-semibold text-ink-900 tabular-nums">{{ $value }}</div>
    <div class="mt-1 flex items-center justify-between text-xs">
        <span class="text-ink-400">{{ $sub }}</span>
        @if($variance && ($variance['pct'] ?? null) !== null)
            <span class="{{ $vClass }} font-medium tabular-nums">{{ $arrow }} {{ number_format(abs($variance['pct']), 1) }}%</span>
        @endif
    </div>
</div>

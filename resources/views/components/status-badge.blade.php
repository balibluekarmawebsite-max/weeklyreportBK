@props(['status'])

@php
    /** @var \App\Enums\ReportStatus $status */
    $status = $status instanceof \App\Enums\ReportStatus
        ? $status
        : \App\Enums\ReportStatus::from($status);

    $classes = [
        'slate'   => 'bg-slate-100 text-slate-700',
        'amber'   => 'bg-amber-100 text-amber-800',
        'sky'     => 'bg-sky-100 text-sky-800',
        'emerald' => 'bg-emerald-100 text-emerald-800',
        'teal'    => 'bg-ink-100 text-ink-700',
    ];
@endphp

<span {{ $attributes->class(['badge', $classes[$status->color()] ?? $classes['slate']]) }}>
    {{ $status->label() }}
</span>

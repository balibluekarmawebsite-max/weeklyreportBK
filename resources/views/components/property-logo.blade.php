@props(['property' => null])
@php
    $code = $property?->code;
    $exists = $code && file_exists(public_path("images/logos/{$code}.webp"));
@endphp
@if($exists)
    <img src="{{ asset("images/logos/{$code}.webp") }}" alt="{{ $property->name }} logo"
        {{ $attributes->merge(['class' => 'w-auto object-contain']) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-lg bg-ink-700 font-serif font-bold text-white']) }}>BK</span>
@endif

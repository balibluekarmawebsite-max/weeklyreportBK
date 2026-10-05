@props(['phase' => '', 'title' => '', 'description' => ''])

<div class="card mx-auto max-w-2xl p-10 text-center">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-sand-100 text-ink-500">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
    </div>
    <h2 class="mt-4 font-serif text-xl font-semibold text-ink-900">{{ $title }}</h2>
    @if($phase)
        <div class="mt-1 text-xs font-medium uppercase tracking-wide text-gold-500">{{ $phase }}</div>
    @endif
    <p class="mx-auto mt-3 max-w-md text-sm text-ink-500">{{ $description }}</p>
    <div class="mt-6">
        <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600">
            Back to dashboard
        </a>
    </div>
</div>

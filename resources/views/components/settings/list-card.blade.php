@props([
    'title' => '',
    'subtitle' => null,
    'items' => [],   // collection of models with ->code, ->name, ->is_active
])

<div class="card p-6">
    <div class="flex items-baseline justify-between">
        <h2 class="font-serif text-lg font-semibold text-ink-900">{{ $title }}</h2>
        @if($subtitle)<span class="text-xs text-ink-400">{{ $subtitle }}</span>@endif
    </div>
    <ul class="mt-4 divide-y divide-sand-100 text-sm">
        @forelse($items as $item)
            <li class="flex items-center justify-between py-2">
                <span class="text-ink-700"><span class="font-mono text-xs text-ink-400">{{ $item->code }}</span> &middot; {{ $item->name }}</span>
                @if($item->is_active)
                    <span class="badge bg-emerald-100 text-emerald-700">Active</span>
                @else
                    <span class="badge bg-slate-100 text-slate-500">Off</span>
                @endif
            </li>
        @empty
            <li class="py-2 text-ink-400">None.</li>
        @endforelse
    </ul>
</div>

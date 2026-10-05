<x-app-layout>
    <x-slot name="title">Settings</x-slot>
    <x-slot name="header">
        <h1 class="font-serif text-2xl font-semibold text-ink-900">Settings</h1>
        <p class="mt-1 text-sm text-ink-500">Foundation data for the dashboard and reports.</p>
    </x-slot>

    <div class="rounded-lg border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-ink-700">
        <strong>Phase 1.</strong> These lists are seeded and shown read-only here. Editing forms (add/edit channels, rate codes, segments, budgets, users &amp; roles, branding, AI &amp; VHP settings) are wired up next.
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Property --}}
        <div class="card p-6">
            <h2 class="font-serif text-lg font-semibold text-ink-900">Property</h2>
            @if($property)
                <dl class="mt-4 grid grid-cols-2 gap-y-3 text-sm">
                    <dt class="text-ink-400">Code</dt><dd class="text-ink-800">{{ $property->code }}</dd>
                    <dt class="text-ink-400">Name</dt><dd class="text-ink-800">{{ $property->name }}</dd>
                    <dt class="text-ink-400">Rooms</dt><dd class="text-ink-800 tabular-nums">{{ $property->rooms_count }}</dd>
                    <dt class="text-ink-400">Currency</dt><dd class="text-ink-800">{{ $property->currency }}</dd>
                    <dt class="text-ink-400">Brand colours</dt>
                    <dd class="flex items-center gap-2">
                        <span class="inline-block h-4 w-4 rounded-full" style="background: {{ $property->primary_color }}"></span>
                        <span class="inline-block h-4 w-4 rounded-full" style="background: {{ $property->accent_color }}"></span>
                    </dd>
                </dl>
            @else
                <p class="mt-4 text-sm text-ink-500">No property configured.</p>
            @endif
        </div>

        {{-- Users & roles --}}
        <div class="card p-6">
            <h2 class="font-serif text-lg font-semibold text-ink-900">Users &amp; roles</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @foreach($users as $user)
                    <li class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-ink-800">{{ $user->name }}</div>
                            <div class="text-xs text-ink-400">{{ $user->email }}</div>
                        </div>
                        <div class="flex gap-1">
                            @forelse($user->roles as $role)
                                <span class="badge bg-ink-100 text-ink-700">{{ $role->name }}</span>
                            @empty
                                <span class="badge bg-slate-100 text-slate-500">No role</span>
                            @endforelse
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Channels --}}
        <x-settings.list-card title="Channels" subtitle="Booking sources (Sections E/F)" :items="$channels" />

        {{-- Market segments --}}
        <x-settings.list-card title="Market segments" subtitle="Section C" :items="$segments" />

        {{-- Rate codes --}}
        <x-settings.list-card title="Rate codes" subtitle="Section D" :items="$rateCodes" />

        {{-- Roles reference --}}
        <div class="card p-6">
            <h2 class="font-serif text-lg font-semibold text-ink-900">Roles</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @foreach($roles as $role)
                    <li>
                        <div class="font-medium text-ink-800">{{ $role->name }}</div>
                        <div class="text-xs text-ink-400">{{ $role->description }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>

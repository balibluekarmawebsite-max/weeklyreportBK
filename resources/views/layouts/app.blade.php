<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ ($title ?? null) ? $title.' · ' : '' }}{{ config('app.name', 'BKDS Reports') }}</title>

        <!-- Fonts: Fraunces (serif headings) + Inter (sans / numbers) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fraunces:400,500,600,700|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans">
        @php($property = \App\Support\Workspace::currentProperty())
        @php($allProperties = \App\Models\Property::where('is_active', true)->orderBy('code')->get())
        <div class="min-h-full lg:flex" x-data="{ sidebar: false }">

            {{-- Sidebar --}}
            <aside
                class="fixed inset-y-0 left-0 z-40 w-64 transform bg-ink-700 px-4 py-5 transition lg:static lg:translate-x-0"
                :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
                <div class="px-2 pb-6">
                    <div class="flex items-center justify-center rounded-lg bg-white px-3 py-2.5">
                        <x-property-logo :property="$property" class="h-11" />
                    </div>
                    <div class="mt-2.5 text-center leading-tight">
                        <div class="font-serif text-sm font-semibold text-white">Weekly Reports</div>
                        <div class="text-xs text-ink-200">{{ $property?->code ?? 'BKDS' }}</div>
                    </div>
                </div>

                <nav class="space-y-1">
                    <x-nav.item :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">Dashboard</x-nav.item>
                    <x-nav.item :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="doc">Weekly Reports</x-nav.item>
                    <x-nav.item :href="route('imports.index')" :active="request()->routeIs('imports.*')" icon="upload">Data Import</x-nav.item>
                    <x-nav.item :href="route('departments.index')" :active="request()->routeIs('departments.*')" icon="users">Department Inputs</x-nav.item>
                    <x-nav.item :href="route('exports.index')" :active="request()->routeIs('exports.*')" icon="download">Export Center</x-nav.item>
                    <x-nav.item :href="route('settings.index')" :active="request()->routeIs('settings.*')" icon="cog">Settings</x-nav.item>
                </nav>

                <div class="absolute inset-x-4 bottom-4 text-xs text-ink-200">
                    <div class="border-t border-ink-600 pt-3">Blue Karma Dijiwa</div>
                </div>
            </aside>

            {{-- Backdrop on mobile --}}
            <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-ink-900/40 lg:hidden"></div>

            {{-- Main column --}}
            <div class="flex min-h-full flex-1 flex-col">
                {{-- Top bar --}}
                <header class="sticky top-0 z-20 border-b border-sand-200 bg-sand-50/90 backdrop-blur">
                    <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <div class="flex items-center gap-3">
                            <button @click="sidebar = !sidebar" class="rounded-md p-2 text-ink-600 hover:bg-sand-200 lg:hidden" aria-label="Toggle menu">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                            </button>

                            {{-- Property switcher --}}
                            <div class="hidden items-center gap-2 sm:flex">
                                <span class="text-xs uppercase tracking-wide text-ink-400">Property</span>
                                <form method="POST" action="{{ route('property.switch') }}">
                                    @csrf
                                    <select name="property_id" onchange="this.form.submit()"
                                        class="rounded-lg border-sand-300 bg-white py-1.5 pl-3 pr-8 text-sm font-medium text-ink-800 focus:border-ink-400 focus:ring-ink-400">
                                        @foreach($allProperties as $p)
                                            <option value="{{ $p->id }}" @selected($property && $p->id === $property->id)>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            {{-- Latest report week for the selected property --}}
                            @php($latestWeek = $property?->reportWeeks()->latest('start_date')->first())
                            <div class="hidden text-right sm:block">
                                <div class="text-xs uppercase tracking-wide text-ink-400">Latest week</div>
                                <div class="text-sm font-medium text-ink-800">{{ $latestWeek?->label ?? '—' }}</div>
                            </div>

                            {{-- User menu --}}
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button class="flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-sm font-medium text-ink-700 shadow-sm ring-1 ring-sand-200 hover:bg-sand-100">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-ink-700 text-xs font-semibold text-white">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                                        <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('profile')" wire:navigate>{{ __('Profile') }}</x-dropdown-link>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                            {{ __('Log Out') }}
                                        </x-dropdown-link>
                                    </form>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>
                </header>

                {{-- Page header slot --}}
                @isset($header)
                    <div class="border-b border-sand-200 bg-white px-4 py-5 sm:px-6">
                        {{ $header }}
                    </div>
                @endisset

                {{-- Page content --}}
                <main class="flex-1 px-4 py-6 sm:px-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>

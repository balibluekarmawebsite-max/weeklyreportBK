<x-app-layout>
    <x-slot name="title">Settings</x-slot>
    <x-slot name="header">
        <h1 class="font-serif text-2xl font-semibold text-ink-900">Settings</h1>
        <p class="mt-1 text-sm text-ink-500">Foundation data for the dashboard and reports.</p>
    </x-slot>

    <div class="rounded-lg border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-ink-700">
        <strong>Note.</strong> The lists below are seeded and shown read-only for now. Editing forms (add/edit channels, rate codes, segments, budgets, users &amp; roles, branding, VHP settings) are wired up next. AI drafting is live — configure it below.
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- AI drafting (Groq) --}}
        <div class="card p-6">
            <h2 class="font-serif text-lg font-semibold text-ink-900">AI drafting (Groq)</h2>
            <p class="mt-1 text-xs text-ink-500">Drafts Section A commentary from the week's figures and rewrites department notes. The API key is read from the server <code class="rounded bg-sand-100 px-1">.env</code> and never stored here.</p>

            <dl class="mt-4 grid grid-cols-2 gap-y-3 text-sm">
                <dt class="text-ink-400">API key</dt>
                <dd>
                    @if($groqKeyConfigured)
                        <span class="badge bg-emerald-100 text-emerald-700">Configured</span>
                    @else
                        <span class="badge bg-slate-100 text-slate-500">Not set</span>
                    @endif
                </dd>
                <dt class="text-ink-400">Active model</dt>
                <dd class="font-medium text-ink-800"><code class="rounded bg-sand-100 px-1 text-xs">{{ $groqModel }}</code></dd>
            </dl>

            @can('manage-settings')
                <form method="POST" action="{{ route('settings.ai.update') }}" class="mt-4">
                    @csrf
                    @method('PUT')
                    <label for="groq_model" class="block text-[10px] uppercase tracking-wide text-ink-400">Model</label>
                    <select id="groq_model" name="groq_model" class="mt-1 w-full rounded border-sand-300 bg-white px-2 py-1.5 text-sm focus:border-ink-400 focus:ring-ink-400">
                        @foreach($groqModels as $value => $label)
                            <option value="{{ $value }}" @selected($value === $groqModel)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($groqKeyConfigured)
                        <p class="mt-1 text-xs {{ $modelsAreLive ? 'text-emerald-600' : 'text-amber-600' }}">
                            @if($modelsAreLive)
                                ✓ This list is live from your Groq account — every option works with your key.
                            @else
                                Couldn't reach Groq to list models — showing common ones. Check your key / connection.
                            @endif
                        </p>
                    @endif
                    @error('groq_model')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <div class="mt-3 flex items-center gap-2">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600">Save AI settings</button>
                        @if(session('status') === 'ai-updated')<span class="text-xs text-emerald-600">Saved.</span>@endif
                    </div>
                </form>
            @else
                <dl class="mt-4 grid grid-cols-2 gap-y-3 text-sm">
                    <dt class="text-ink-400">Model</dt>
                    <dd class="text-ink-800">{{ $groqModels[$groqModel] ?? $groqModel }}</dd>
                </dl>
                <p class="mt-3 text-xs text-ink-400">Only an administrator can change the AI model.</p>
            @endcan

            @unless($groqKeyConfigured)
                <p class="mt-3 text-xs text-ink-400">Get a free key at <span class="text-ink-600">console.groq.com/keys</span>, then set <code class="rounded bg-sand-100 px-1">GROQ_API_KEY</code> in <code class="rounded bg-sand-100 px-1">.env</code> to switch AI on.</p>
            @endunless
        </div>

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

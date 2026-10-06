<x-app-layout>
    <x-slot name="title">Weekly Reports</x-slot>
    <x-slot name="header">
        <div class="flex items-end justify-between">
            <div>
                <h1 class="font-serif text-2xl font-semibold text-ink-900">Weekly Reports</h1>
                <p class="mt-1 text-sm text-ink-500">{{ $property?->name }}</p>
            </div>
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600">
                    + New week
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" x-transition
                     class="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-sand-200 bg-white p-4 shadow-lg">
                    <form method="POST" action="{{ route('reports.store') }}">
                        @csrf
                        <input type="hidden" name="property_id" value="{{ $property?->id }}">
                        <label class="block text-xs font-medium text-ink-500">Pick any day in the week</label>
                        <p class="mb-2 mt-0.5 text-[11px] text-ink-400">Snaps to that week’s Friday (Fri → Thu).</p>
                        <input type="date" name="start_date" value="{{ now()->previous(\Carbon\Carbon::FRIDAY)->toDateString() }}" required
                               class="w-full rounded-lg border-sand-300 text-sm focus:border-ink-400 focus:ring-ink-400">
                        <button type="submit" class="mt-3 w-full rounded-lg bg-ink-700 px-4 py-2 text-sm font-medium text-white hover:bg-ink-600">Create week</button>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    <div x-data="{ delId:null, delLabel:'', delAction:'', typed:'' }">
    @if(session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" x-data x-init="setTimeout(() => $el.remove(), 5000)">{{ session('status') }}</div>
    @endif
    <div class="card overflow-hidden">
        @if($weeks->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-sm text-ink-500">No report weeks yet.</p>
                <p class="mt-1 text-xs text-ink-400">Weeks will be created and filled starting in Phase 2 (data entry).</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-sand-200 text-left text-xs uppercase tracking-wide text-ink-400">
                        <th class="px-6 py-3 font-medium">Period</th>
                        <th class="px-6 py-3 font-medium">Week</th>
                        <th class="px-6 py-3 font-medium">Owner</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Last edited</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach($weeks as $week)
                        <tr class="hover:bg-sand-50">
                            <td class="px-6 py-3 font-medium text-ink-800">{{ $week->label ?? $week->start_date->format('d M Y') }}</td>
                            <td class="px-6 py-3 text-ink-600 tabular-nums">W{{ $week->week_number }} · {{ $week->year }}</td>
                            <td class="px-6 py-3 text-ink-600">{{ $week->owner?->name ?? '–' }}</td>
                            <td class="px-6 py-3"><x-status-badge :status="$week->status" /></td>
                            <td class="px-6 py-3 text-ink-500">{{ $week->updated_at?->diffForHumans() }}</td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('reports.show', $week) }}" wire:navigate class="font-medium text-ink-600 hover:text-ink-800">Open →</a>
                                @can('manage-settings')
                                    <button type="button" class="ml-4 text-ink-300 hover:text-red-500"
                                        @click="delId={{ $week->id }}; delLabel=@js($week->label ?? $week->start_date->format('d M Y')); delAction='{{ url('reports') }}/{{ $week->id }}'; typed=''">Delete</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">
        {{ $weeks->links() }}
    </div>

    @can('manage-settings')
        {{-- Delete confirmation modal (type the week label to confirm) --}}
        <div x-show="delId !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink-900/40 p-4" @keydown.escape.window="delId=null">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @click.outside="delId=null">
                <h3 class="font-serif text-lg font-semibold text-ink-900">Delete this week?</h3>
                <p class="mt-2 text-sm text-ink-600">This permanently deletes <span class="font-medium text-ink-800" x-text="delLabel"></span> and <strong>all of its section data</strong>. This cannot be undone.</p>
                <form method="POST" x-bind:action="delAction" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <label class="block text-xs text-ink-500">Type the week label to confirm:</label>
                    <input name="confirm" x-model="typed" :placeholder="delLabel" autocomplete="off"
                        class="mt-1 w-full rounded border-sand-300 text-sm focus:border-red-400 focus:ring-red-400">
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="delId=null" class="rounded-lg px-4 py-2 text-sm text-ink-600 hover:bg-sand-100">Cancel</button>
                        <button type="submit" :disabled="typed.trim() !== delLabel.trim()"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40">Delete permanently</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
    </div>
</x-app-layout>

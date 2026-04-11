<x-layouts.app>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Content Calendar
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">

                {{-- Mode tabs --}}
                <div class="flex rounded-md shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'multi-month' => 'Multi-Month'] as $key => $label)
                        <button wire:click="setMode('{{ $key }}')"
                            class="px-4 py-2 text-sm font-medium transition-colors
                                {{ $mode === $key
                                    ? 'bg-zinc-800 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                    : 'bg-white dark:bg-zinc-900 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-zinc-800' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Navigation --}}
                <div class="flex items-center gap-2">
                    <button wire:click="navigate('prev')"
                        class="px-3 py-2 rounded bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-zinc-800 text-gray-700 dark:text-gray-300 text-sm">
                        &larr; Prev
                    </button>
                    <button wire:click="goToday"
                        class="px-3 py-2 rounded bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-zinc-800 text-gray-700 dark:text-gray-300 text-sm font-medium">
                        Today
                    </button>
                    <button wire:click="navigate('next')"
                        class="px-3 py-2 rounded bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-zinc-800 text-gray-700 dark:text-gray-300 text-sm">
                        Next &rarr;
                    </button>
                </div>

                {{-- Add button --}}
                <button wire:click="openCreate('{{ now()->format('Y-m-d\TH:i') }}')"
                    class="px-4 py-2 rounded bg-zinc-800 hover:bg-zinc-700 dark:bg-zinc-100 dark:hover:bg-zinc-200 text-white dark:text-zinc-900 text-sm font-medium flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Entry
                </button>
            </div>

            {{-- Calendar Views --}}

            @if($mode === 'monthly')
                @php
                    $base = \Carbon\Carbon::createFromFormat('Y-m', $currentDate);
                    $startOfMonth = $base->copy()->startOfMonth();
                    $endOfMonth   = $base->copy()->endOfMonth();
                    $startGrid    = $startOfMonth->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                    $endGrid      = $endOfMonth->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                    $entriesByDay = collect($entries)->groupBy(fn($e) => \Carbon\Carbon::parse($e['scheduled_at'])->format('Y-m-d'));
                @endphp

                <div class="mb-2 text-center text-lg font-semibold text-gray-800 dark:text-gray-200">
                    {{ $base->format('F Y') }}
                </div>

                <div class="grid grid-cols-7 text-center text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
                        <div class="py-1">{{ $d }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 border-l border-t border-gray-200 dark:border-gray-700">
                    @for($day = $startGrid->copy(); $day->lte($endGrid); $day->addDay())
                        @php
                            $key   = $day->format('Y-m-d');
                            $isCurrentMonth = $day->month === $base->month;
                            $isToday = $day->isToday();
                            $dayEntries = $entriesByDay->get($key, collect());
                        @endphp
                        <div class="min-h-[90px] border-r border-b border-gray-200 dark:border-gray-700 p-1
                            {{ $isCurrentMonth ? 'bg-white dark:bg-zinc-900' : 'bg-gray-50 dark:bg-zinc-950' }}">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs {{ $isToday ? 'bg-blue-500 text-white rounded-full w-5 h-5 flex items-center justify-center' : ($isCurrentMonth ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600') }}">
                                    {{ $day->day }}
                                </span>
                                <button wire:click="openCreate('{{ $key }}T09:00')"
                                    class="text-gray-300 dark:text-gray-600 hover:text-blue-400 text-xs leading-none">+</button>
                            </div>
                            @foreach($dayEntries as $entry)
                                <button wire:click="openEdit({{ $entry['id'] }})"
                                    class="w-full text-left text-xs px-1 py-0.5 rounded mb-0.5 truncate text-white font-medium"
                                    style="background-color: {{ $entry['color'] ?? '#3b82f6' }}">
                                    {{ $entry['title'] }}
                                </button>
                            @endforeach
                        </div>
                    @endfor
                </div>

            @elseif($mode === 'weekly')
                @php
                    $base = \Carbon\Carbon::createFromFormat('Y-m', $currentDate);
                    $weekStart = $base->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                    $weekEnd   = $weekStart->copy()->addDays(6);
                    $entriesByDay = collect($entries)->groupBy(fn($e) => \Carbon\Carbon::parse($e['scheduled_at'])->format('Y-m-d'));
                @endphp

                <div class="mb-2 text-center text-lg font-semibold text-gray-800 dark:text-gray-200">
                    {{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}
                </div>

                <div class="grid grid-cols-7 gap-2">
                    @for($day = $weekStart->copy(); $day->lte($weekEnd); $day->addDay())
                        @php
                            $key = $day->format('Y-m-d');
                            $dayEntries = $entriesByDay->get($key, collect());
                        @endphp
                        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-gray-700 rounded-md p-2 min-h-[120px]">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex justify-between">
                                <span>{{ $day->format('D d') }}</span>
                                <button wire:click="openCreate('{{ $key }}T09:00')" class="text-gray-300 hover:text-blue-400">+</button>
                            </div>
                            @foreach($dayEntries as $entry)
                                <button wire:click="openEdit({{ $entry['id'] }})"
                                    class="w-full text-left text-xs px-1 py-0.5 rounded mb-1 truncate text-white"
                                    style="background-color: {{ $entry['color'] ?? '#3b82f6' }}">
                                    {{ $entry['title'] }}
                                </button>
                            @endforeach
                        </div>
                    @endfor
                </div>

            @elseif($mode === 'daily')
                @php
                    $base = \Carbon\Carbon::createFromFormat('Y-m', $currentDate);
                    $today = now()->format('Y-m-d');
                @endphp

                <div class="mb-4 text-center text-lg font-semibold text-gray-800 dark:text-gray-200">
                    {{ $base->format('l, d F Y') }}
                </div>

                <div class="max-w-2xl mx-auto space-y-2">
                    @forelse($entries as $entry)
                        <button wire:click="openEdit({{ $entry['id'] }})"
                            class="w-full text-left p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-zinc-900 flex items-center gap-3 hover:shadow-sm transition">
                            <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $entry['color'] ?? '#3b82f6' }}"></span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800 dark:text-gray-200 truncate">{{ $entry['title'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($entry['scheduled_at'])->format('H:i') }} · {{ $entry['type'] }} · {{ $entry['status'] }}</p>
                            </div>
                        </button>
                    @empty
                        <p class="text-center text-gray-400 dark:text-gray-600 py-12">No entries scheduled.</p>
                    @endforelse

                    <button wire:click="openCreate('{{ $base->format('Y-m-d') }}T09:00')"
                        class="w-full border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg p-4 text-gray-400 hover:text-blue-400 hover:border-blue-400 transition text-sm">
                        + Add entry for this day
                    </button>
                </div>

            @elseif($mode === 'multi-month')
                @php
                    $base = \Carbon\Carbon::createFromFormat('Y-m', $currentDate);
                    $entriesByDay = collect($entries)->groupBy(fn($e) => \Carbon\Carbon::parse($e['scheduled_at'])->format('Y-m-d'));
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @for($m = 0; $m < 3; $m++)
                        @php
                            $month = $base->copy()->addMonths($m);
                            $startOfMonth = $month->copy()->startOfMonth();
                            $endOfMonth   = $month->copy()->endOfMonth();
                            $startGrid    = $startOfMonth->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                            $endGrid      = $endOfMonth->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                        @endphp
                        <div>
                            <div class="text-center font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $month->format('F Y') }}</div>
                            <div class="grid grid-cols-7 text-center text-xs text-gray-400 mb-1">
                                @foreach(['M','T','W','T','F','S','S'] as $d)
                                    <div>{{ $d }}</div>
                                @endforeach
                            </div>
                            <div class="grid grid-cols-7 border-l border-t border-gray-200 dark:border-gray-700 text-xs">
                                @for($day = $startGrid->copy(); $day->lte($endGrid); $day->addDay())
                                    @php
                                        $key = $day->format('Y-m-d');
                                        $inMonth = $day->month === $month->month;
                                        $hasDot  = $entriesByDay->has($key);
                                    @endphp
                                    <button wire:click="openCreate('{{ $key }}T09:00')"
                                        class="border-r border-b border-gray-200 dark:border-gray-700 py-1 flex flex-col items-center
                                            {{ $inMonth ? 'bg-white dark:bg-zinc-900' : 'bg-gray-50 dark:bg-zinc-950' }}
                                            {{ $day->isToday() ? 'ring-1 ring-inset ring-blue-400' : '' }}">
                                        <span class="{{ $inMonth ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-600' }}">{{ $day->day }}</span>
                                        @if($hasDot)
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mt-0.5"></span>
                                        @endif
                                    </button>
                                @endfor
                            </div>
                        </div>
                    @endfor
                </div>
            @endif

        </div>
    </div>

    {{-- CRUD Modal --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    {{ empty($editingEntry['id']) ? 'New Entry' : 'Edit Entry' }}
                </h3>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="save" class="p-6 space-y-4">

                {{-- Title --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                    <input type="text" wire:model="editingEntry.title"
                        class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Entry title">
                    @error('editingEntry.title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Type + Status row --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                        <select wire:model="editingEntry.type"
                            class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach(['post','course','workshop','youtube_video','linkedin','other'] as $t)
                                <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                            @endforeach
                        </select>
                        @error('editingEntry.type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select wire:model="editingEntry.status"
                            class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @foreach(['draft','scheduled','published','cancelled'] as $s)
                                <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                        @error('editingEntry.status') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Scheduled at + Color row --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Scheduled At</label>
                        <input type="datetime-local" wire:model="editingEntry.scheduled_at"
                            class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @error('editingEntry.scheduled_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="editingEntry.color"
                                class="h-9 w-12 rounded border border-gray-300 dark:border-gray-600 cursor-pointer">
                            <input type="text" wire:model="editingEntry.color"
                                class="flex-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                maxlength="7" placeholder="#3b82f6">
                        </div>
                        @error('editingEntry.color') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                    <textarea wire:model="editingEntry.notes" rows="3"
                        class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Optional notes..."></textarea>
                    @error('editingEntry.notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-between pt-2">
                    @if(!empty($editingEntry['id']))
                        <button type="button" wire:click="delete({{ $editingEntry['id'] }})"
                            wire:confirm="Delete this entry?"
                            class="px-4 py-2 rounded bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-400 text-sm font-medium">
                            Delete
                        </button>
                    @else
                        <span></span>
                    @endif

                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('showModal', false)"
                            class="px-4 py-2 rounded bg-gray-100 hover:bg-gray-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-gray-700 dark:text-gray-300 text-sm font-medium">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 rounded bg-zinc-800 hover:bg-zinc-700 dark:bg-zinc-100 dark:hover:bg-zinc-200 text-white dark:text-zinc-900 text-sm font-medium">
                            Save
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

</x-layouts.app>

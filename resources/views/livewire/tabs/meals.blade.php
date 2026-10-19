{{-- The week's meal plan from Notion. Tap a meal to see its ingredients; one button puts them all on the shopping list. --}}
<div class="h-full overflow-y-auto" wire:poll.{{ $refresh }}s x-data="{ open: null }">
    @if (! $configured)
        <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
            <flux:heading size="xl">Meals</flux:heading>
            <flux:text>Set NOTION_TOKEN and the Notion database ids to connect the meal plan.</flux:text>
        </div>
    @elseif ($week === null)
        <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
            <flux:heading size="xl">Meals</flux:heading>
            <flux:text>The meal plan is unavailable right now.</flux:text>
        </div>
    @else
        @php($pending = collect($week['days'])->pluck('meals')->merge($week['flexible'])->flatten(1)->where('added', false)->count())
        <div class="mb-4 flex items-center justify-between gap-3">
            <flux:heading size="lg">This week</flux:heading>
            <div class="flex items-center gap-3">
                @if ($added !== null)
                    <flux:text>{{ $added === 0 ? 'Nothing new to add.' : "Added {$added} ".($added === 1 ? 'item' : 'items').' to the list.' }}</flux:text>
                @endif
                @if ($pending > 0)
                    <button type="button" wire:click="addToList" wire:loading.attr="disabled" class="rounded-xl text-[0.95rem] text-zinc-100 active:bg-zinc-700" style="min-height: 3rem; padding: 0.5rem 1.25rem; background: rgba(63, 63, 70, 0.8);">
                        <span wire:loading.remove wire:target="addToList">Add ingredients to the list</span>
                        <span wire:loading wire:target="addToList">Adding…</span>
                    </button>
                @endif
            </div>
        </div>

        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr));">
            @foreach ($week['days'] as $day)
                <div wire:key="day-{{ $day['date'] }}" class="rounded-2xl border bg-zinc-900 p-4 {{ $day['today'] ? 'border-zinc-500' : 'border-zinc-800' }}">
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading class="{{ $day['past'] ? 'opacity-60' : '' }}">{{ $day['label'] }}@if ($day['today']) <span class="text-zinc-400">· today</span>@endif</flux:heading>
                        <button type="button" wire:click="pick('{{ $day['date'] }}')" class="shrink-0 rounded-xl text-[0.9rem] text-zinc-100 active:bg-zinc-700" style="min-height: 2.75rem; padding: 0.25rem 1rem; background: rgba(63, 63, 70, 0.8);">{{ $picking === $day['date'] ? 'Cancel' : ($day['meals'] ? 'Add' : 'Choose') }}</button>
                    </div>
                    @if ($picking === $day['date'])
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($week['flexible'] as $option)
                                <button type="button" wire:key="opt-{{ $day['date'] }}-{{ $option['id'] }}" wire:click="assign('{{ $option['id'] }}', '{{ $day['date'] }}')" class="rounded-full text-[0.95rem] text-zinc-100 active:bg-zinc-600" style="min-height: 2.75rem; padding: 0.25rem 1rem; background: rgba(63, 63, 70, 0.9);">{{ $option['name'] }}</button>
                            @empty
                                <flux:text>No meals left to pick from this week.</flux:text>
                            @endforelse
                        </div>
                    @endif
                    @forelse ($day['meals'] as $meal)
                        <div wire:key="meal-{{ $meal['id'] }}" class="mt-2">
                            <button type="button" @click="open = open === '{{ $meal['id'] }}' ? null : '{{ $meal['id'] }}'" class="flex w-full items-center justify-between gap-3 rounded-xl text-left active:bg-zinc-800" style="min-height: 3rem; padding: 0.25rem 0.5rem;">
                                <span class="text-[1.05rem]">{{ $meal['name'] }}</span>
                                @if ($meal['added'])<span class="text-[0.75rem] text-emerald-400">on the list</span>@endif
                            </button>
                            <div x-show="open === '{{ $meal['id'] }}'" x-cloak style="padding: 0.25rem 0.5rem;">
                                <button type="button" wire:click="clear('{{ $meal['id'] }}')" wire:loading.attr="disabled" wire:target="clear('{{ $meal['id'] }}')" class="mb-2 rounded-xl text-[0.9rem] text-zinc-100 active:bg-zinc-700" style="min-height: 2.75rem; padding: 0.25rem 1rem; background: rgba(63, 63, 70, 0.8);">Clear this day</button>
                                @if ($meal['notes'])<flux:text class="mb-1">{{ $meal['notes'] }}</flux:text>@endif
                                @forelse ($meal['ingredients'] as $ingredient)
                                    <div class="text-[0.95rem] {{ $ingredient['need'] ? 'text-zinc-100' : 'text-zinc-400' }}">• {{ $ingredient['name'] }}@if ($ingredient['need']) <span class="text-zinc-500">(on the list)</span>@endif</div>
                                @empty
                                    <flux:text>No ingredients listed.</flux:text>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <flux:text class="mt-2">Nothing planned.</flux:text>
                    @endforelse
                </div>
            @endforeach
        </div>

        @if (count($week['flexible']))
            {{-- Planned for the week but not for a particular day: choose one day-of. --}}
            <flux:heading size="lg" class="mb-2 mt-6">Pick one for the week</flux:heading>
            <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr));">
                @foreach ($week['flexible'] as $meal)
                    <div wire:key="flex-{{ $meal['id'] }}" class="rounded-2xl border border-zinc-800 bg-zinc-900 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <button type="button" @click="open = open === '{{ $meal['id'] }}' ? null : '{{ $meal['id'] }}'" class="flex-1 rounded-xl text-left active:bg-zinc-800" style="min-height: 3rem; padding: 0.25rem 0.5rem;">
                                <span class="text-[1.05rem]">{{ $meal['name'] }}</span>
                                @if ($meal['added'])<span class="ml-2 text-[0.75rem] text-emerald-400">on the list</span>@endif
                            </button>
                            <button type="button" wire:click="makeToday('{{ $meal['id'] }}')" wire:loading.attr="disabled" wire:target="makeToday('{{ $meal['id'] }}')" class="shrink-0 rounded-xl text-[0.9rem] text-zinc-100 active:bg-zinc-700" style="min-height: 3rem; padding: 0.5rem 1rem; background: rgba(63, 63, 70, 0.8);">Tonight</button>
                        </div>
                        <div x-show="open === '{{ $meal['id'] }}'" x-cloak style="padding: 0.25rem 0.5rem;">
                            @if ($meal['notes'])<flux:text class="mb-1">{{ $meal['notes'] }}</flux:text>@endif
                            @forelse ($meal['ingredients'] as $ingredient)
                                <div class="text-[0.95rem] {{ $ingredient['need'] ? 'text-zinc-100' : 'text-zinc-400' }}">• {{ $ingredient['name'] }}@if ($ingredient['need']) <span class="text-zinc-500">(on the list)</span>@endif</div>
                            @empty
                                <flux:text>No ingredients listed.</flux:text>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>

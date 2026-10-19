{{-- The week's meal plan from Notion. Tap a day or a meal for its ingredients (and to assign or clear it). --}}
<div class="h-full" wire:poll.{{ $refresh }}s @tab-left.window="if ($el.closest('[data-tab]')?.dataset.tab === $event.detail) { if ($wire.picking || $wire.viewing) $wire.close() }">
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
        @php($chosen = $picking ? collect($week['days'])->firstWhere('date', $picking) : null)
        @php($looking = $viewing ? collect($week['flexible'])->firstWhere('id', $viewing) : null)
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr));">
            @foreach ($week['days'] as $day)
                <button type="button" wire:key="day-{{ $day['date'] }}" wire:click="pick('{{ $day['date'] }}')" class="hd-card flex flex-col items-stretch justify-start rounded-2xl border bg-zinc-900 text-left active:bg-zinc-800 {{ $day['today'] ? 'border-white' : 'border-zinc-800' }}" style="min-height: 7rem;{{ $day['today'] ? ' outline: 3px solid #fff; outline-offset: -3px;' : '' }}">
                    <div class="hd-card-head"><flux:heading class="{{ $day['past'] ? 'opacity-60' : '' }}">{{ $day['label'] }}@if ($day['today']) <span class="text-zinc-400">· today</span>@endif</flux:heading></div>
                    <div class="p-4 pt-3">
                    @forelse ($day['meals'] as $meal)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[1.05rem]">{{ $meal['name'] }}</span>
                            @if ($meal['added'])<span class="shrink-0 text-[0.75rem] text-emerald-400">on the list</span>@endif
                        </div>
                    @empty
                        <flux:text>Nothing planned. Tap to choose.</flux:text>
                    @endforelse
                    </div>
                </button>
            @endforeach
        </div>

        @if (count($week['flexible']))
            {{-- On hand for the week but not pinned to a day yet. --}}
            <div class="hd-card mt-4 w-full rounded-2xl border border-zinc-800 bg-zinc-900">
            <div class="hd-card-head"><flux:heading>On hand, not yet scheduled</flux:heading></div>
            <ul class="px-4 pb-2">
                @foreach ($week['flexible'] as $meal)
                    <li wire:key="flex-{{ $meal['id'] }}" class="border-b border-zinc-800">
                        <button type="button" wire:click="view('{{ $meal['id'] }}')" class="flex w-full items-center justify-between gap-3 text-left active:bg-zinc-800" style="min-height: 3rem; padding: 0.25rem 0.5rem;">
                            <span class="text-[1.05rem]">{{ $meal['name'] }}</span>
                            @if ($meal['added'])<span class="shrink-0 text-[0.75rem] text-emerald-400">on the list</span>@endif
                        </button>
                    </li>
                @endforeach
            </ul>
            </div>
        @endif

        @if ($chosen)
            {{-- One day: its meal with the ingredients and a way to clear it, or the week's meals to choose from. --}}
            <div class="flex items-center justify-center p-6" style="position: fixed; inset: 0; z-index: 40; background: rgba(0, 0, 0, 0.75);" wire:click="close" @keydown.escape.window="$wire.close()" role="dialog" aria-modal="true" aria-label="{{ $chosen['label'] }}">
                <div class="w-full overflow-y-auto rounded-2xl border border-zinc-700 bg-zinc-900 p-6" style="max-width: 34rem; max-height: 100%;" wire:click.stop>
                    <flux:heading size="lg">{{ $chosen['label'] }}</flux:heading>

                    @forelse ($chosen['meals'] as $meal)
                        <div wire:key="lb-{{ $meal['id'] }}" class="mt-4">
                            <div class="text-[1.25rem] font-medium">{{ $meal['name'] }}@if ($meal['added']) <span class="ml-2 text-[0.8rem] font-normal text-emerald-400">on the list</span>@endif</div>
                            @if ($meal['notes'])<flux:text class="mt-1">{{ $meal['notes'] }}</flux:text>@endif
                            <div class="mt-2">
                                @forelse ($meal['ingredients'] as $ingredient)
                                    <div class="text-[1rem] {{ $ingredient['need'] ? 'text-zinc-100' : 'text-zinc-400' }}">• {{ $ingredient['name'] }}@if ($ingredient['need']) <span class="text-zinc-500">(on the list)</span>@endif</div>
                                @empty
                                    <flux:text>No ingredients listed.</flux:text>
                                @endforelse
                            </div>
                            <button type="button" wire:click="clear('{{ $meal['id'] }}')" wire:loading.attr="disabled" wire:target="clear('{{ $meal['id'] }}')" class="mt-3 rounded-xl text-[0.95rem] text-zinc-100 active:bg-zinc-700" style="min-height: 3rem; padding: 0.5rem 1.25rem; background: rgba(63, 63, 70, 0.8);">Clear this meal</button>
                        </div>
                    @empty
                        <flux:text class="mt-1">Nothing planned for this day.</flux:text>
                        @if (count($week['flexible']))
                            <div class="mt-4 flex flex-col">
                                @foreach ($week['flexible'] as $option)
                                    <button type="button" wire:key="opt-{{ $option['id'] }}" wire:click="assign('{{ $option['id'] }}', '{{ $chosen['date'] }}')" wire:loading.attr="disabled" class="border-b border-zinc-800 text-left text-[1.05rem] active:bg-zinc-800" style="min-height: 3rem; padding: 0.25rem 0.5rem;">{{ $option['name'] }}</button>
                                @endforeach
                            </div>
                        @else
                            <flux:text class="mt-1">No meals left to pick from this week.</flux:text>
                        @endif
                    @endforelse

                    <button type="button" wire:click="close" class="mt-5 rounded-xl bg-zinc-800 active:bg-zinc-700" style="min-height: 3rem; padding: 0.75rem 1.5rem;">Close</button>
                </div>
            </div>
        @endif

        @if ($looking)
            {{-- One meal that has no day yet: its ingredients, and optionally a day to give it. --}}
            <div class="flex items-center justify-center p-6" style="position: fixed; inset: 0; z-index: 40; background: rgba(0, 0, 0, 0.75);" wire:click="close" @keydown.escape.window="$wire.close()" role="dialog" aria-modal="true" aria-label="{{ $looking['name'] }}">
                <div class="w-full overflow-y-auto rounded-2xl border border-zinc-700 bg-zinc-900 p-6" style="max-width: 34rem; max-height: 100%;" wire:click.stop>
                    <div class="text-[1.25rem] font-medium">{{ $looking['name'] }}@if ($looking['added']) <span class="ml-2 text-[0.8rem] font-normal text-emerald-400">on the list</span>@endif</div>
                    @if ($looking['notes'])<flux:text class="mt-1">{{ $looking['notes'] }}</flux:text>@endif
                    <div class="mt-2">
                        @forelse ($looking['ingredients'] as $ingredient)
                            <div class="text-[1rem] {{ $ingredient['need'] ? 'text-zinc-100' : 'text-zinc-400' }}">• {{ $ingredient['name'] }}@if ($ingredient['need']) <span class="text-zinc-500">(on the list)</span>@endif</div>
                        @empty
                            <flux:text>No ingredients listed.</flux:text>
                        @endforelse
                    </div>

                    <flux:text class="mb-2 mt-5">Give it a day (or leave it open for the week):</flux:text>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($week['days'] as $day)
                            @continue($day['past'])
                            <button type="button" wire:key="assign-{{ $day['date'] }}" wire:click="assign('{{ $looking['id'] }}', '{{ $day['date'] }}')" wire:loading.attr="disabled" class="rounded-full text-[0.95rem] text-zinc-100 active:bg-zinc-600" style="min-height: 2.75rem; padding: 0.25rem 1rem; background: rgba(63, 63, 70, 0.9);">{{ $day['today'] ? 'Today' : substr($day['label'], 0, 3).' '.substr(strrchr($day['label'], ' '), 1) }}</button>
                        @endforeach
                    </div>

                    <button type="button" wire:click="close" class="mt-5 rounded-xl bg-zinc-800 active:bg-zinc-700" style="min-height: 3rem; padding: 0.75rem 1.5rem;">Close</button>
                </div>
            </div>
        @endif
    @endif
</div>

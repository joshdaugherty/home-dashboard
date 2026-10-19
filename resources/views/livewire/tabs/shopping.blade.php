{{-- Items marked "Need it" in Notion, by store. Tap an item to take it off the list; type to add one. --}}
<div class="h-full" wire:poll.{{ $refresh }}s>
    @if (! $configured)
        <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
            <flux:heading size="xl">Shopping</flux:heading>
            <flux:text>Set NOTION_TOKEN and the Notion database ids to connect the shopping list.</flux:text>
        </div>
    @elseif ($groups === null)
        <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
            <flux:heading size="xl">Shopping</flux:heading>
            <flux:text>The shopping list is unavailable right now.</flux:text>
        </div>
    @else
        <form wire:submit="add" class="mb-4 flex gap-3">
            <input
                type="text"
                wire:model.live.debounce.250ms="query"
                placeholder="Add an item…"
                autocomplete="off"
                enterkeyhint="done"
                maxlength="120"
                class="min-w-0 flex-1 rounded-xl text-[1.05rem] text-zinc-100 placeholder-zinc-500"
                style="min-height: 3rem; padding: 0.25rem 1rem; background: rgba(39, 39, 42, 0.9); border: 1px solid #52525b;"
            >
            <button type="submit" class="shrink-0 rounded-xl text-[1rem] text-zinc-100 active:bg-zinc-600" style="min-height: 3rem; padding: 0.25rem 1.5rem; background: rgba(63, 63, 70, 0.9);">Add</button>
        </form>

        @if ($query !== '' && count($suggestions))
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ($suggestions as $suggestion)
                    <button type="button" wire:key="suggest-{{ $suggestion['id'] }}" wire:click="addItem('{{ $suggestion['id'] }}')" class="rounded-full text-[0.95rem] text-zinc-100 active:bg-zinc-600" style="min-height: 2.75rem; padding: 0.25rem 1rem; background: rgba(63, 63, 70, 0.9);">+ {{ $suggestion['name'] }}</button>
                @endforeach
            </div>
        @elseif ($notice)
            <flux:text class="mb-4">{{ $notice }}</flux:text>
        @endif

        @if (count($groups) === 0)
            <div class="flex flex-col items-center justify-center gap-2 py-16 text-center">
                <flux:heading size="xl">Nothing to buy</flux:heading>
                <flux:text>Everything on the list has been checked off.</flux:text>
            </div>
        @endif

        @foreach ($groups as $group)
            <div wire:key="store-{{ $group['name'] }}" class="mb-4 rounded-2xl border border-zinc-800 bg-zinc-900 p-4">
                <div class="flex items-baseline justify-between gap-3">
                    <flux:heading>{{ $group['name'] }}</flux:heading>
                    <span class="text-[0.85rem] text-zinc-500">{{ count($group['items']) }}</span>
                </div>
                <ul class="mt-2" style="column-width: 16rem; column-gap: 1rem;">
                    @foreach ($group['items'] as $item)
                        <li wire:key="item-{{ $item['id'] }}" style="break-inside: avoid;">
                            <button
                                type="button"
                                wire:click="tick('{{ $item['id'] }}')"
                                wire:loading.class="opacity-40"
                                wire:target="tick('{{ $item['id'] }}')"
                                class="flex w-full items-center gap-3 rounded-xl text-left active:bg-zinc-800"
                                style="min-height: 3rem; padding: 0.25rem 0.5rem;"
                            >
                                <span class="shrink-0 rounded-full border border-zinc-600" style="width: 1.5rem; height: 1.5rem;" aria-hidden="true"></span>
                                <span class="text-[1.05rem]">{{ $item['name'] }}@if ($item['note']) <span class="text-zinc-400">({{ $item['note'] }})</span>@endif</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif
</div>

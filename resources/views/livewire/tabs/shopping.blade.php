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
            <div wire:key="store-{{ $group['name'] }}" class="hd-card mb-4 rounded-2xl border border-zinc-800 bg-zinc-900">
                <div class="hd-card-head">
                    <flux:heading>{{ $group['name'] }}</flux:heading>
                    <span class="text-[0.85rem] text-zinc-500">{{ count($group['items']) }}</span>
                </div>
                <ul class="p-4 pt-2" style="display: flex; flex-wrap: wrap; column-gap: 1rem;">
                    @foreach ($group['items'] as $item)
                        <li wire:key="item-{{ $item['id'] }}" style="display: flex; align-items: center; min-width: 16rem; max-width: 100%;">
                            <button
                                type="button"
                                wire:click="tick('{{ $item['id'] }}')"
                                wire:loading.class="opacity-40"
                                wire:target="tick('{{ $item['id'] }}')"
                                class="flex min-w-0 flex-1 items-center gap-3 rounded-xl text-left active:bg-zinc-800"
                                style="min-height: 3rem; padding: 0.25rem 0.5rem;"
                            >
                                <span class="shrink-0 rounded-full border border-zinc-600" style="width: 1.5rem; height: 1.5rem;" aria-hidden="true"></span>
                                <span class="text-[1.05rem]">{{ $item['name'] }}@if ($item['note']) <span class="text-zinc-400">({{ $item['note'] }})</span>@endif</span>
                            </button>
                            <button type="button" wire:click="edit('{{ $item['id'] }}')" aria-label="Quantity of {{ $item['name'] }}" class="shrink-0 rounded-xl text-[0.95rem] active:bg-zinc-700 {{ $item['qty'] > 1 ? 'text-zinc-100' : 'text-zinc-500' }}" style="min-height: 2.75rem; min-width: 2.75rem; padding: 0.25rem 0.6rem; background: rgba(63, 63, 70, {{ $item['qty'] > 1 ? '0.8' : '0.35' }});">×{{ $item['qty'] }}</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        @php($editingItem = $editing ? collect($groups)->pluck('items')->flatten(1)->firstWhere('id', $editing) : null)
        @if ($editingItem)
            {{-- How many to buy. --}}
            <div class="flex items-center justify-center p-6" style="position: fixed; inset: 0; z-index: 40; background: rgba(0, 0, 0, 0.75);" wire:click="close" @keydown.escape.window="$wire.close()" role="dialog" aria-modal="true" aria-label="Quantity of {{ $editingItem['name'] }}">
                <div class="w-full rounded-2xl border border-zinc-700 bg-zinc-900 p-6 text-center" style="max-width: 24rem;" wire:click.stop>
                    <div class="text-[1.25rem] font-medium">{{ $editingItem['name'] }}</div>
                    <div class="mt-4 flex items-center justify-center gap-6">
                        <button type="button" wire:click="setQuantity('{{ $editingItem['id'] }}', {{ $editingItem['qty'] - 1 }})" aria-label="One fewer" class="rounded-full text-[1.75rem] text-zinc-100 active:bg-zinc-600" style="width: 4rem; height: 4rem; background: rgba(63, 63, 70, 0.9);">−</button>
                        <span class="text-[2.5rem] tabular-nums" style="min-width: 3.5rem;">{{ $editingItem['qty'] }}</span>
                        <button type="button" wire:click="setQuantity('{{ $editingItem['id'] }}', {{ $editingItem['qty'] + 1 }})" aria-label="One more" class="rounded-full text-[1.75rem] text-zinc-100 active:bg-zinc-600" style="width: 4rem; height: 4rem; background: rgba(63, 63, 70, 0.9);">+</button>
                    </div>
                    <button type="button" wire:click="close" class="mt-6 rounded-xl bg-zinc-800 active:bg-zinc-700" style="min-height: 3rem; padding: 0.75rem 1.5rem;">Done</button>
                </div>
            </div>
        @endif
    @endif
</div>

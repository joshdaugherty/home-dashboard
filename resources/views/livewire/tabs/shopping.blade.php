{{-- Items marked "Need it" in Notion, by aisle in walking order. Tap one to take it off the list. --}}
<div class="h-full overflow-y-auto" wire:poll.{{ $refresh }}s>
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
    @elseif (count($groups) === 0)
        <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
            <flux:heading size="xl">Nothing to buy</flux:heading>
            <flux:text>Everything on the list has been checked off.</flux:text>
        </div>
    @else
        <div style="column-width: 17rem; column-gap: 1rem;">
            @foreach ($groups as $group)
                <div wire:key="aisle-{{ $group['id'] ?: 'unsorted' }}" class="mb-4 rounded-2xl border border-zinc-800 bg-zinc-900 p-4" style="break-inside: avoid;">
                    <div class="flex items-baseline justify-between gap-3">
                        <flux:heading>{{ $group['name'] }}</flux:heading>
                        @if (($group['store'] ?? null) === 'Costco')
                            <span class="text-[0.75rem] uppercase tracking-wide text-red-400">Costco</span>
                        @endif
                    </div>
                    <ul class="mt-2">
                        @foreach ($group['items'] as $item)
                            <li wire:key="item-{{ $item['id'] }}">
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
        </div>
    @endif
</div>

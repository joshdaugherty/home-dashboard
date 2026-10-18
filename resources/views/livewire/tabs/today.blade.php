<div class="flex h-full flex-col justify-between gap-6" x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)">
    <div>
        <div class="text-[clamp(3.5rem,14vmin,11rem)] font-light leading-none tabular-nums" x-text="now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })"></div>
        <flux:heading size="xl" class="mt-2" x-text="now.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' })"></flux:heading>
    </div>

    {{-- Responsive card grid: 1 column when narrow, up to 3 when wide. --}}
    <div class="grid grid-cols-[repeat(auto-fit,minmax(16rem,1fr))] gap-4">
        @foreach (['Weather', 'Up next', 'Chores due'] as $card)
            <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-4">
                <flux:heading>{{ $card }}</flux:heading>
                <flux:text class="mt-1">Coming soon.</flux:text>
            </div>
        @endforeach
    </div>
</div>

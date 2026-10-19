<div
    class="flex h-full flex-col justify-between gap-6"
    wire:poll.{{ $refresh }}s
    x-data="{
        now: new Date(),
        tz: @js($timezone),
        fmt(o) { return new Intl.DateTimeFormat([], { timeZone: this.tz, ...o }).format(this.now); },
    }"
    x-init="setInterval(() => now = new Date(), 1000)"
>
    <div>
        <div class="text-[clamp(3.5rem,14vmin,11rem)] font-light leading-none tabular-nums" x-text="fmt({ hour: 'numeric', minute: '2-digit' })"></div>
        <flux:heading size="xl" class="mt-2" x-text="fmt({ weekday: 'long', month: 'long', day: 'numeric' })"></flux:heading>
    </div>

    {{-- 1 column when narrow, 2 when wide. --}}
    <div class="grid min-h-0 grid-cols-[repeat(auto-fit,minmax(18rem,1fr))] gap-4">
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-4">
            <flux:heading>Weather</flux:heading>
            @if (! $weatherConfigured)
                <flux:text class="mt-1">Set DASHBOARD_LATITUDE and DASHBOARD_LONGITUDE.</flux:text>
            @elseif (! $forecast)
                <flux:text class="mt-1">Weather unavailable right now.</flux:text>
            @else
                <div class="mt-2 flex items-center gap-4">
                    <div class="text-[clamp(2.5rem,8vmin,5rem)] leading-none" aria-hidden="true">{{ $forecast['current']['icon'] }}</div>
                    <div>
                        <div class="text-[clamp(2rem,6vmin,4rem)] font-light leading-none tabular-nums">{{ $forecast['current']['temp'] }}{{ $forecast['unit'] }}</div>
                        <flux:text class="mt-1">{{ $forecast['current']['label'] }} · feels {{ $forecast['current']['feels'] }}°</flux:text>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-5 gap-2 text-center">
                    @foreach ($forecast['daily'] as $day)
                        <div class="rounded-xl bg-zinc-800/60 px-1 py-2">
                            <div class="text-[0.8rem] text-zinc-400">{{ $day['name'] }}</div>
                            <div class="text-[1.4rem] leading-tight" aria-hidden="true">{{ $day['icon'] }}</div>
                            <div class="text-[0.85rem] tabular-nums">{{ $day['high'] }}° <span class="text-zinc-500">{{ $day['low'] }}°</span></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-4">
            <flux:heading>Up next</flux:heading>
            @if ($calendarError)
                <flux:text class="mt-1">{{ $calendarError }}</flux:text>
            @else
                @forelse ($upcoming as $event)
                    <div class="mt-3 flex items-start gap-3">
                        <span class="mt-[0.45rem] size-3 shrink-0 rounded-full" style="background-color: {{ $event['color'] }}"></span>
                        <div class="min-w-0">
                            <div class="truncate">{{ $event['title'] }}</div>
                            <div class="text-[0.85rem] text-zinc-400">{{ $event['day'] }} · {{ $event['time'] }}@if ($event['location']) · {{ $event['location'] }}@endif</div>
                        </div>
                    </div>
                @empty
                    <flux:text class="mt-1">Nothing coming up.</flux:text>
                @endforelse
                @if ($calendarsFailed)
                    <flux:text class="mt-3 text-amber-400">Couldn't load: {{ implode(', ', $calendarsFailed) }}</flux:text>
                @endif
            @endif
        </div>
    </div>
</div>

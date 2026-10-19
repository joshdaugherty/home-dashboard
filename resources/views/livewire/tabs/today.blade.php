<div
    class="flex h-full flex-col justify-between gap-6" style="position: relative; isolation: isolate;"
    wire:poll.{{ $refresh }}s
    x-data="{
        now: new Date(),
        tz: @js($timezone),
        fmt(o) { return new Intl.DateTimeFormat([], { timeZone: this.tz, ...o }).format(this.now); },
    }"
    x-init="setInterval(() => now = new Date(), 1000)"
>
    <div>
        <div class="text-[clamp(3.5rem,14vmin,11rem)] font-light leading-none tabular-nums" style="text-shadow: 0 2px 14px rgba(0, 0, 0, 0.75), 0 1px 3px rgba(0, 0, 0, 0.6);" x-text="fmt({ hour: 'numeric', minute: '2-digit' })"></div>
        <flux:heading size="xl" class="mt-2" style="text-shadow: 0 2px 14px rgba(0, 0, 0, 0.75), 0 1px 3px rgba(0, 0, 0, 0.6);" x-text="fmt({ weekday: 'long', month: 'long', day: 'numeric' })"></flux:heading>
    </div>

    @foreach ($alerts as $alert)
        {{-- Weather alerts: shown only while one is active. --}}
        <div role="alert" class="rounded-2xl border text-[0.95rem]" style="padding: 0.75rem 1rem; {{ in_array($alert['severity'], ['Extreme', 'Severe']) ? 'background: rgba(127, 29, 29, 0.85); border-color: #f87171; color: #fee2e2;' : 'background: rgba(120, 53, 15, 0.85); border-color: #fbbf24; color: #fef3c7;' }}">
            <span class="font-semibold">⚠ {{ $alert['event'] }}</span>
            <span class="opacity-90"> · {{ $alert['headline'] }}</span>
        </div>
    @endforeach
    {{-- 1 column when narrow, 2 when wide. --}}
    <div class="grid min-h-0 grid-cols-[repeat(auto-fit,minmax(18rem,1fr))] gap-4">
        <div class="relative rounded-2xl border border-zinc-800 bg-zinc-900 p-4" @if ($background) style="background: rgba(24, 24, 27, 0.9); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);" @endif>
            @if ($radarUrl && $forecast)
                <button type="button" @click="$dispatch('open-radar', @js($radarUrl))" class="rounded-xl text-[0.85rem] text-zinc-300 active:bg-zinc-700" style="position: absolute; top: 0.75rem; right: 0.75rem; min-height: 2.75rem; padding: 0.5rem 1rem; background: rgba(63, 63, 70, 0.5);">Radar</button>
            @endif
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
                        <flux:text class="mt-1 text-[0.85rem]">
                            @if (($forecast['current']['humidity'] ?? null) !== null)Humidity {{ ($forecast['current']['humidity'] ?? null) }}%@endif
                            @if (($forecast['current']['wind'] ?? null) !== null) · Wind {{ ($forecast['current']['wind'] ?? null) }} {{ $forecast['wind_unit'] ?? '' }}@endif
                            @if (($forecast['current']['rain'] ?? null) !== null) · Rain {{ ($forecast['current']['rain'] ?? null) }}%@endif
                        </flux:text>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-5 gap-2 text-center">
                    @foreach ($forecast['daily'] as $day)
                        <div class="rounded-xl bg-zinc-800/60 px-1 py-2">
                            <div class="text-[0.8rem] text-zinc-400">{{ $day['name'] }}</div>
                            <div class="text-[1.4rem] leading-tight" aria-hidden="true">{{ $day['icon'] }}</div>
                            <div class="text-[0.85rem] tabular-nums">{{ $day['high'] }}° <span class="text-zinc-500">{{ $day['low'] }}°</span></div>
                            @if (! empty($day['precip']))
                                <div class="text-[0.75rem] tabular-nums text-sky-300" title="Chance of rain">💧{{ $day['precip'] }}%</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="hd-card rounded-2xl border border-zinc-800 bg-zinc-900" @if ($background) style="background: rgba(24, 24, 27, 0.9); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);" @endif>
            <div class="hd-card-head"><flux:heading>Up next</flux:heading></div>
            <div class="px-4 pb-4">
            @if ($calendarError)
                <flux:text class="mt-1">{{ $calendarError }}</flux:text>
            @else
                @forelse ($upcoming as $event)
                    <div class="mt-3 flex items-start gap-3">
                        <x-home-dashboard::avatars :people="$event['people']" :swatch="$event['swatch']" />
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

    {{-- Tell the shell about today's picture (it shows the corner icon, lightbox and idle box). Re-keyed per picture so a new one re-announces itself. --}}
    <div wire:key="art-{{ md5($background['url'] ?? '') }}" x-data x-init="$dispatch('background-info', @js($background))" hidden></div>
</div>

<div
    class="flex h-full flex-col justify-between gap-6" style="position: relative; isolation: isolate;"
    wire:poll.{{ $refresh }}s
    x-data="{
        now: new Date(),
        radar: false,
        radarSrc: '',
        radarTimer: null,
        openRadar(url) { this.radarSrc = url + '?t=' + Date.now(); this.radar = true; clearTimeout(this.radarTimer); this.radarTimer = setTimeout(() => this.radar = false, 60000); },
        tz: @js($timezone),
        fmt(o) { return new Intl.DateTimeFormat([], { timeZone: this.tz, ...o }).format(this.now); },
    }"
    x-init="setInterval(() => now = new Date(), 1000)"
>
    @if ($background)
        {{-- Daily painting behind everything. It sits under a dark wash so text stays readable, and a failed load leaves the plain background. --}}
        <div style="position: fixed; inset: 0; z-index: -1; background: #09090b;" aria-hidden="true">
            <img src="{{ $background['url'] }}" alt="" referrerpolicy="no-referrer" onerror="this.remove()" style="width: 100%; height: 100%; object-fit: cover;">
            <div style="position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(9,9,11,.78), rgba(9,9,11,.62) 50%, rgba(9,9,11,.86));"></div>
        </div>
    @endif
    <div>
        <div class="text-[clamp(3.5rem,14vmin,11rem)] font-light leading-none tabular-nums" x-text="fmt({ hour: 'numeric', minute: '2-digit' })"></div>
        <flux:heading size="xl" class="mt-2" x-text="fmt({ weekday: 'long', month: 'long', day: 'numeric' })"></flux:heading>
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
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-4" @if ($background) style="background: rgba(24, 24, 27, 0.8); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);" @endif>
            <div class="flex items-center justify-between gap-3">
                <flux:heading>Weather</flux:heading>
                @if ($radarUrl && $forecast)
                    <button type="button" @click="openRadar(@js($radarUrl))" class="rounded-xl text-[0.85rem] text-zinc-300 active:bg-zinc-700" style="min-height: 2.75rem; padding: 0.5rem 1rem; background: rgba(63, 63, 70, 0.5);">Radar</button>
                @endif
            </div>
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
                            @if ($forecast['current']['humidity'] !== null)Humidity {{ $forecast['current']['humidity'] }}%@endif
                            @if ($forecast['current']['wind'] !== null) · Wind {{ $forecast['current']['wind'] }} {{ $forecast['wind_unit'] }}@endif
                            @if ($forecast['current']['rain'] !== null) · Rain {{ $forecast['current']['rain'] }}%@endif
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

        <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-4" @if ($background) style="background: rgba(24, 24, 27, 0.8); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);" @endif>
            <flux:heading>Up next</flux:heading>
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

    @if ($radarUrl)
        {{-- NWS radar loop, loaded only when opened (it is a few hundred KB) and closed after a minute. --}}
        <div x-show="radar" x-cloak x-transition.opacity class="flex items-center justify-center p-4" style="position: fixed; inset: 0; z-index: 30; background: rgba(0, 0, 0, 0.85);" @click="radar = false" @keydown.escape.window="radar = false" role="dialog" aria-modal="true" aria-label="Weather radar">
            <div class="text-center" @click.stop>
                <img :src="radar ? radarSrc : ''" alt="Animated weather radar" referrerpolicy="no-referrer" style="max-width: 100%; max-height: 70vh; border-radius: 0.75rem; background: #09090b;">
                <div class="mt-2 text-[0.75rem] text-zinc-400">Radar: NOAA / National Weather Service</div>
                <button type="button" class="mt-3 rounded-xl bg-zinc-800 active:bg-zinc-700" style="min-height: 3rem; padding: 0.75rem 1.5rem;" @click="radar = false">Close</button>
            </div>
        </div>
    @endif

    {{-- Tell the shell about today's picture (it shows the corner icon, lightbox and idle box). Re-keyed per picture so a new one re-announces itself. --}}
    <div wire:key="art-{{ md5($background['url'] ?? '') }}" x-data x-init="$dispatch('background-info', @js($background))" hidden></div>
</div>

{{--
    Responsive kiosk shell. h-full inside a dvh/dvw body: no page scroll, content area
    flexes, tab bar sits at the bottom. All sizing is rem/percentage based (rem scales
    with the viewport via the layout's clamp()).
--}}
<div
    class="relative flex h-full w-full flex-col"
    x-data="{
        tab: @js($homeTab),
        home: @js($homeTab),
        ambient: false,
        now: new Date(),
        last: Date.now(),
        idleAfter: {{ $idleAfter }},
        ambientAfter: {{ $ambientAfter }},
        version: @js($version),
        settings: @js($settingsVersion),
        versionUrl: @js($versionUrl),
        init() {
            setInterval(() => {
                this.now = new Date();
                const idle = (Date.now() - this.last) / 1000;
                if (this.ambientAfter > 0 && idle >= this.ambientAfter) this.ambient = true;
                if (this.idleAfter > 0 && idle >= this.idleAfter) this.tab = this.home;
            }, 1000);

            @if ($versionEvery > 0)
                setInterval(() => this.checkVersion(), {{ $versionEvery * 1000 }});
            @endif
        },
        // Reload after a deploy or a settings change (possibly made on another device), but never mid-touch: only when the frame has been left alone.
        async checkVersion() {
            if ((Date.now() - this.last) / 1000 < 30) return;
            try {
                const res = await fetch(this.versionUrl, { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (!res.ok || !(res.headers.get('content-type') || '').includes('json')) return;
                const { version, settings } = await res.json();
                if ((version && version !== this.version) || (settings && settings !== this.settings)) location.reload();
            } catch (e) { /* offline or mid-deploy: try again next tick */ }
        },
        touch() { this.last = Date.now(); this.ambient = false; },
        get drift() { const m = this.now.getMinutes(); return { x: Math.sin(m) * 8, y: Math.cos(m * 1.7) * 8 }; },
    }"
    @pointerdown.window="touch()"
    @keydown.window="touch()"
>
    {{-- Content: each pane scrolls on its own; the page never does. --}}
    <main class="relative min-h-0 flex-1">
        @foreach ($tabs as $key => $tab)
            <section
                x-show="tab === @js($key)"
                x-cloak
                class="absolute inset-0 overflow-y-auto overscroll-contain p-[clamp(0.75rem,2vmin,2rem)]"
                aria-label="{{ $tab['label'] }}"
            >
                @livewire($tab['component'], array_merge(['lazy' => 'on-load'], $tab['params'] ?? []), key('pane-'.$key))
            </section>
        @endforeach
    </main>

    {{-- Bottom tab bar: equal-width, 48px+ touch targets, never wraps. --}}
    <nav class="relative z-10 flex shrink-0 border-t border-zinc-800 bg-zinc-900/90 pb-[env(safe-area-inset-bottom)]" aria-label="Dashboard sections">
        @foreach ($tabs as $key => $tab)
            <button
                type="button"
                @click="tab = @js($key)"
                class="flex min-h-12 flex-1 flex-col items-center justify-center gap-1 px-2 py-[clamp(0.4rem,1.2vh,0.9rem)] text-[0.8rem] font-medium transition-colors"
                :class="tab === @js($key) ? 'text-white bg-zinc-800' : 'text-zinc-400 active:bg-zinc-800/60'"
                :aria-current="tab === @js($key) ? 'page' : null"
            >
                <x-home-dashboard::icon :name="$tab['icon']" class="size-[1.75rem]" />
                <span>{{ $tab['label'] }}</span>
            </button>
        @endforeach
        <a
            href="{{ route(config('home-dashboard.settings_route', 'home-dashboard.settings')) }}"
            class="flex min-h-12 w-[clamp(4.5rem,9vw,7rem)] shrink-0 flex-col items-center justify-center gap-1 px-2 py-[clamp(0.4rem,1.2vh,0.9rem)] text-[0.8rem] font-medium text-zinc-400 active:bg-zinc-800/60"
        >
            <x-home-dashboard::icon name="cog" class="size-[1.75rem]" />
            <span>Settings</span>
        </a>
    </nav>

    {{-- Ambient mode: dim clock, drifting a few px each minute to avoid burn-in. Any touch wakes it. --}}
    <div
        x-show="ambient"
        x-cloak
        x-transition.opacity.duration.700ms
        class="absolute inset-0 z-50 flex items-center justify-center bg-black"
        @click.stop
    >
        <div class="text-center text-zinc-500" :style="`transform: translate(${drift.x}px, ${drift.y}px)`">
            <div class="text-[clamp(4rem,18vmin,14rem)] font-extralight leading-none tabular-nums" x-text="now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })"></div>
            <div class="mt-2 text-[clamp(1rem,3.5vmin,2.5rem)]" x-text="now.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' })"></div>
        </div>
    </div>
</div>

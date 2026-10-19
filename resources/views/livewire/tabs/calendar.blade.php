{{--
    Month grid + agenda. All events for the window are sent once and navigation is
    client-side (no round-trips on touch). Dates are plain Y-m-d strings in the
    configured timezone, so the device's own timezone never matters.
--}}
<div
    class="@container h-full"
    wire:poll.{{ $refresh }}s
    x-data="{
        days: @js($days),
        today: @js($today),
        month: @js(substr($today, 0, 7)),
        selected: @js($today),
        people: @js($people),
        who: 'all',
        visible(list) {
            return (list || []).filter(e => (this.who === 'all' || (this.who === 'shared' ? !(e.person_ids || []).length : (e.person_ids || []).includes(this.who))));
        },
        dow: ['S', 'M', 'T', 'W', 'T', 'F', 'S'],
        get title() {
            const [y, m] = this.month.split('-').map(Number);
            return new Date(Date.UTC(y, m - 1, 1)).toLocaleDateString([], { month: 'long', year: 'numeric', timeZone: 'UTC' });
        },
        get cells() {
            const [y, m] = this.month.split('-').map(Number);
            const first = Date.UTC(y, m - 1, 1);
            const start = first - new Date(first).getUTCDay() * 864e5;
            return Array.from({ length: 42 }, (_, i) => {
                const d = new Date(start + i * 864e5);
                const key = d.toISOString().slice(0, 10);
                return { key, n: d.getUTCDate(), out: d.getUTCMonth() !== m - 1, events: this.visible(this.days[key]) };
            });
        },
        get agenda() { return this.visible(this.days[this.selected]); },
        get selectedTitle() {
            const [y, m, d] = this.selected.split('-').map(Number);
            return new Date(Date.UTC(y, m - 1, d)).toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', timeZone: 'UTC' });
        },
        shift(n) {
            const [y, m] = this.month.split('-').map(Number);
            this.month = new Date(Date.UTC(y, m - 1 + n, 1)).toISOString().slice(0, 7);
        },
        goToday() { this.month = this.today.slice(0, 7); this.selected = this.today; },
    }"
>
    @if ($error || ! $configured)
        <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
            <flux:heading size="xl">Calendar</flux:heading>
            <flux:text>{{ $error ?: 'Set DASHBOARD_CALENDARS to show your calendars.' }}</flux:text>
        </div>
    @else
        <div class="flex h-full flex-col gap-3">
        {{-- Person / shared filter. Filtering is client-side over the events already sent. --}}
        @if (count($people) + ($hasShared ? 1 : 0) > 1)
        <div class="flex shrink-0 flex-wrap items-center gap-x-6 gap-y-2">
            <div class="flex flex-wrap gap-1" role="group" aria-label="Whose">
                <button type="button" @click="who = 'all'" class="min-h-12 rounded-xl border border-white px-4 text-[0.9rem]" :class="who === 'all' ? 'bg-white text-zinc-950' : 'text-white active:bg-zinc-700'" :style="who === 'all' ? '' : 'background: rgba(24, 24, 27, 0.6);'" :aria-pressed="who === 'all'">Everyone</button>
                @if ($hasShared)
                <button type="button" @click="who = 'shared'" class="min-h-12 rounded-xl border border-white px-4 text-[0.9rem]" :class="who === 'shared' ? 'bg-white text-zinc-950' : 'text-white active:bg-zinc-700'" :style="who === 'shared' ? '' : 'background: rgba(24, 24, 27, 0.6);'" :aria-pressed="who === 'shared'">Shared</button>
                @endif
                <template x-for="p in people" :key="p.id">
                    <button type="button" @click="who = p.id" class="min-h-12 rounded-xl border border-white px-4 text-[0.9rem]" :class="who === p.id ? 'bg-white text-zinc-950' : 'text-white active:bg-zinc-700'" :style="who === p.id ? '' : 'background: rgba(24, 24, 27, 0.6);'" :aria-pressed="who === p.id" x-text="p.name"></button>
                </template>
            </div>
        </div>
        @endif
        <div class="flex min-h-0 flex-1 flex-col gap-4 @3xl:flex-row">
            {{-- Month grid --}}
            <section class="flex min-h-0 flex-[3] flex-col" aria-label="Month">
                <div class="mb-2 flex items-center gap-2">
                    <button type="button" @click="shift(-1)" class="flex min-h-12 min-w-12 items-center justify-center rounded-xl border border-white text-2xl text-white active:bg-zinc-700" style="background: rgba(24, 24, 27, 0.6);" aria-label="Previous month">‹</button>
                    <flux:heading size="lg" class="flex-1 text-center" x-text="title"></flux:heading>
                    <button type="button" @click="shift(1)" class="flex min-h-12 min-w-12 items-center justify-center rounded-xl border border-white text-2xl text-white active:bg-zinc-700" style="background: rgba(24, 24, 27, 0.6);" aria-label="Next month">›</button>
                    <button type="button" @click="goToday()" class="min-h-12 rounded-xl border border-white px-4 text-[0.9rem] text-white active:bg-zinc-700" style="background: rgba(24, 24, 27, 0.6);">Today</button>
                </div>
                <div class="grid grid-cols-7 text-center text-[0.8rem] text-zinc-500">
                    <template x-for="(d, i) in dow" :key="i"><div x-text="d"></div></template>
                </div>
                <div class="grid min-h-0 flex-1 grid-cols-7 grid-rows-6 gap-1">
                    <template x-for="c in cells" :key="c.key">
                        <button
                            type="button"
                            @click="selected = c.key"
                            class="flex min-h-0 min-w-0 flex-col items-center justify-start gap-1 rounded-xl border p-1"
                            :class="[
                                selected === c.key ? 'border-white bg-zinc-800' : 'border-zinc-800 bg-zinc-900 active:bg-zinc-800',
                                c.out ? 'text-zinc-600' : '',
                            ]"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full text-[0.9rem] tabular-nums"
                                :class="c.key === today ? 'bg-white font-semibold text-zinc-950' : ''"
                                x-text="c.n"
                            ></span>
                            <span class="flex flex-wrap justify-center gap-[3px]">
                                <template x-for="(e, i) in c.events.slice(0, 4)" :key="i">
                                    <span class="size-[0.45rem] rounded-full" :style="`background:${e.swatch}`"></span>
                                </template>
                            </span>
                        </button>
                    </template>
                </div>
            </section>

            {{-- Agenda for the selected day --}}
            <section class="flex min-h-0 flex-[2] flex-col overflow-y-auto rounded-2xl border border-zinc-800 bg-zinc-900 p-4" aria-label="Agenda">
                <flux:heading x-text="selectedTitle"></flux:heading>
                <template x-if="agenda.length === 0">
                    <flux:text class="mt-2">Nothing scheduled.</flux:text>
                </template>
                <template x-for="(e, i) in agenda" :key="i">
                    <div class="mt-3 flex items-start gap-3">
                        <template x-if="e.people && e.people.length">
                            <div class="flex shrink-0 -space-x-2" :aria-label="e.people.map(p => p.name).join(', ')">
                                <template x-for="p in e.people.slice(0, 3)" :key="p.id">
                                    <span class="relative inline-flex size-9 items-center justify-center rounded-full text-[0.9rem] font-semibold text-zinc-900 ring-2 ring-zinc-50 dark:ring-zinc-900" :style="`background:${p.color}`" :title="p.name">
                                        <span x-text="p.initial"></span>
                                        <template x-if="p.avatar">
                                            <img :src="p.avatar" alt="" loading="lazy" referrerpolicy="no-referrer" x-on:error="$el.remove()" class="absolute inset-0 size-full rounded-full object-cover">
                                        </template>
                                    </span>
                                </template>
                                <template x-if="e.people.length > 3">
                                    <span class="relative inline-flex size-9 items-center justify-center rounded-full bg-zinc-700 text-[0.8rem] font-semibold text-zinc-100 ring-2 ring-zinc-900" x-text="`+${e.people.length - 3}`"></span>
                                </template>
                            </div>
                        </template>
                        <template x-if="!(e.people && e.people.length)">
                            <span class="mt-[0.45rem] size-3 shrink-0 rounded-full" :style="`background:${e.swatch}`"></span>
                        </template>
                        <div class="min-w-0">
                            <div x-text="e.title"></div>
                            <div class="text-[0.85rem] text-zinc-400">
                                <span x-text="e.time"></span>
                                <template x-if="e.location"><span x-text="' · ' + e.location"></span></template>
                                <span class="text-zinc-500" x-text="' · ' + e.calendar"></span>
                            </div>
                        </div>
                    </div>
                </template>
                @if ($failed)
                    <flux:text class="mt-4 text-amber-400">Couldn't load: {{ implode(', ', $failed) }}</flux:text>
                @endif
            </section>
        </div>
        </div>
    @endif
</div>

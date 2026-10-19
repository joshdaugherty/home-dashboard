{{-- Settings: plain scrolling page inside the kiosk layout (the layout itself never scrolls). --}}
<div @class(['h-full select-text overflow-y-auto p-[clamp(0.75rem,2vmin,2rem)]' => ! $embedded, 'select-text' => $embedded])>
    <div @class(['mx-auto max-w-3xl space-y-8 pb-16' => ! $embedded, 'space-y-8' => $embedded])>
        @unless ($embedded)
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="xl">Settings</flux:heading>
            <flux:button href="{{ $back }}" class="min-h-12">Back to dashboard</flux:button>
        </div>
        @endunless

        @if ($status)
            <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" role="status">{{ $status }}</div>
        @endif

        <form wire:submit="save" class="space-y-10">
            {{-- People --}}
            @if ($section === 'all' || $section === 'people')
            <section class="space-y-4">
                <div>
                    @unless ($embedded && $section !== 'all')
                    <flux:heading size="lg">People</flux:heading>
                    @endunless
                    <flux:text class="mt-1">Events on a person's calendar show their picture. Pictures come from Gravatar using the email address; without one, their initial is shown. Only a hash of the address reaches the display.</flux:text>
                </div>

                @foreach ($people as $i => $person)
                    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 p-4" wire:key="person-{{ $i }}-{{ $person['id'] }}">
                        <div class="min-w-40 flex-1">
                            <flux:input wire:model="people.{{ $i }}.name" label="Name" />
                            @error("people.{$i}.name") <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="min-w-56 flex-[2]">
                            <flux:input wire:model="people.{{ $i }}.email" type="email" label="Gravatar email" autocomplete="off" />
                            @error("people.{$i}.email") <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <flux:button type="button" wire:click="removePerson({{ $i }})" variant="danger" class="min-h-12">Remove</flux:button>
                    </div>
                @endforeach

                <flux:button type="button" wire:click="addPerson" class="min-h-12">Add a person</flux:button>
            </section>
            @endif

            {{-- Calendars --}}
            @if ($section === 'all' || $section === 'calendars')
            <section class="space-y-4">
                <div>
                    @unless ($embedded && $section !== 'all')
                    <flux:heading size="lg">Calendars</flux:heading>
                    @endunless
                    <flux:text class="mt-1">Read-only iCal links, for example Google Calendar's "Secret address in iCal format". Saved addresses are never shown again; type a new one to replace it.</flux:text>
                </div>

                @forelse ($feeds as $i => $feed)
                    <div class="space-y-3 rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 p-4" wire:key="feed-{{ $i }}-{{ $feed['id'] }}">
                        <div class="flex flex-wrap gap-3">
                            <div class="min-w-48 flex-1">
                                <flux:input wire:model="feeds.{{ $i }}.name" label="Name" placeholder="Family" />
                                @error("feeds.{$i}.name") <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <flux:text class="mb-1 text-sm font-medium">Color</flux:text>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($palette as $name => $hex)
                                        <button
                                            type="button"
                                            wire:click="$set('feeds.{{ $i }}.color', '{{ $name }}')"
                                            class="size-12 rounded-full border-2 {{ $feed['color'] === $name ? 'border-zinc-900 dark:border-white' : 'border-transparent' }}"
                                            style="background-color: {{ $hex }}"
                                            aria-label="{{ ucfirst($name) }}"
                                            aria-pressed="{{ $feed['color'] === $name ? 'true' : 'false' }}"
                                        ></button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <flux:select wire:model="feeds.{{ $i }}.category" label="Category">
                            @foreach ($categories as $category)
                                <flux:select.option value="{{ $category }}">{{ $category }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if ($people)
                            <flux:select wire:model="feeds.{{ $i }}.person" label="Whose calendar">
                                <flux:select.option value="">Nobody in particular</flux:select.option>
                                @foreach ($people as $person)
                                    <flux:select.option value="{{ $person['id'] }}">{{ $person['name'] ?: 'Unnamed' }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        <div>
                            <flux:input
                                wire:model="feeds.{{ $i }}.url"
                                type="password"
                                label="Calendar address"
                                :placeholder="$feed['saved'] ? 'Saved. Type a new address to replace it.' : 'https://calendar.google.com/calendar/ical/.../basic.ics'"
                                autocomplete="off"
                            />
                            @error("feeds.{$i}.url") <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button type="button" wire:click="testFeed({{ $i }})" class="min-h-12">Test</flux:button>
                            <flux:button type="button" wire:click="moveFeed({{ $i }}, -1)" class="min-h-12 min-w-12" aria-label="Move up">↑</flux:button>
                            <flux:button type="button" wire:click="moveFeed({{ $i }}, 1)" class="min-h-12 min-w-12" aria-label="Move down">↓</flux:button>
                            <flux:button type="button" wire:click="removeFeed({{ $i }})" variant="danger" class="ml-auto min-h-12">Remove</flux:button>
                        </div>

                        @if (isset($tests[$feed['id']]))
                            <flux:text class="{{ str_starts_with($tests[$feed['id']], 'Works') ? 'text-emerald-400' : 'text-amber-400' }}">{{ $tests[$feed['id']] }}</flux:text>
                        @endif
                    </div>
                @empty
                    <flux:text>No calendars yet.</flux:text>
                @endforelse

                <flux:button type="button" wire:click="addFeed" class="min-h-12">Add a calendar</flux:button>
            </section>
            @endif

            {{-- Location --}}
            @if ($section === 'all' || $section === 'location')
            <section class="space-y-4">
                <div>
                    @unless ($embedded && $section !== 'all')
                    <flux:heading size="lg">Location and weather</flux:heading>
                    @endunless
                    <flux:text class="mt-1">Used for the weather and for all times shown.</flux:text>
                </div>

                <div class="flex gap-2">
                    <div class="flex-1">
                        <flux:input wire:model="placeQuery" wire:keydown.enter.prevent="searchPlace" label="Find a place" placeholder="Conway, Arkansas" />
                    </div>
                    <flux:button type="button" wire:click="searchPlace" class="mt-6 min-h-12">Search</flux:button>
                </div>

                @if ($places)
                    <div class="space-y-2">
                        @foreach ($places as $p => $place)
                            <button type="button" wire:click="pickPlace({{ $p }})" class="block min-h-12 w-full rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 px-4 py-3 text-left active:bg-zinc-200 dark:active:bg-zinc-800">
                                {{ $place['label'] }}
                                <span class="text-sm text-zinc-500">· {{ $place['tz'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model="latitude" label="Latitude" inputmode="decimal" />
                        @error('latitude') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="longitude" label="Longitude" inputmode="decimal" />
                        @error('longitude') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="timezone" label="Time zone" placeholder="America/Chicago" />
                        @error('timezone') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select wire:model="units" label="Units">
                            <flux:select.option value="imperial">Fahrenheit</flux:select.option>
                            <flux:select.option value="metric">Celsius</flux:select.option>
                        </flux:select>
                    </div>
                </div>
            </section>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                <flux:button type="submit" variant="primary" class="min-h-12">Save</flux:button>
                @if ($section === 'all' || $section === 'location')
                <flux:button type="button" wire:click="resetToEnvironment" wire:confirm="Forget everything saved here and use the environment variables again?" class="min-h-12">Use environment defaults</flux:button>
                @endif
            </div>
        </form>
    </div>
</div>

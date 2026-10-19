# home-dashboard

A responsive, touch-first family dashboard kiosk for a wall-mounted photo frame, built as a Laravel package on Livewire 4 and the free tier of Flux UI. It fills whatever viewport it is given: no fixed pixel size and no page scroll. A bottom tab bar switches panes. After 60 s idle it returns to Today, and after 120 s it shows a dim ambient clock over the day's picture. Nothing relies on hover.

Requires PHP 8.3+, Laravel 13, Livewire 4.1+ and Flux 2.13+ (free components only).

**Status: pre-release (0.x).** Expect changes between minor versions.

## What is on the frame

| Tab | What it shows |
| --- | --- |
| Today | Clock and date over a rotating background picture, weather (with a radar lightbox and alerts), and the next events from your Personal calendars |
| Calendar | Month grid plus a per-day agenda for every calendar that is not a class schedule or assignments, with an Everyone / Shared / per-person filter |
| Class Schedules | The same calendar view, limited to the Class Schedule category |
| Assignments | The same calendar view, limited to the Assignments category |
| Shopping | The shopping list from Notion, grouped by store; tap an item to take it off the list, or type in the box to add one (known items are suggested as you type) |
| Meals | This week's meal plan from Notion; tap a meal for its ingredients, or add them all to the shopping list |
| Settings | A link (not a pane) to the settings pages |

The tab list is config (`tabs`): reorder, remove, or point a tab at your own Livewire component. A tab's `params` are passed to its component; the calendar panes use `only` and `except` to filter by category.

On Today, two buttons sit in the upper right: a moon that starts the idle screen straight away, and an ⓘ that opens a lightbox about the background picture. Lightboxes close on a tap, Escape, the idle screen, or switching tabs.

## Install into a Laravel app

The host app should be built on the Livewire starter kit (Tailwind 4, Flux, Vite). It owns authentication and deployment.

```bash
composer require joshdaugherty/home-dashboard
php artisan home-dashboard:install   # publishes config, adds the Tailwind @source line
php artisan migrate                  # the package loads its own migration
npm run build
```

The dashboard is served at `/` by default (`DASHBOARD_PREFIX` to change it) behind the `web` and `auth` middleware. Remove any existing `/` route in the app. Set `route.enabled` to `false` in the config to register your own route to `JoshDaugherty\HomeDashboard\Livewire\Shell`.

The layout loads the Vite entries in `config('home-dashboard.vite')` (`resources/css/app.css` and `resources/js/app.js` by default) and hardcodes dark mode. Tailwind has to scan the package views (the install command adds the `@source` line), so rebuild assets after updating the package; a running `npm run dev` may not notice new classes in `vendor/` until it is restarted.

## Single-user login for the frame

No public registration is needed. Set these env vars and run `php artisan home-dashboard:user` on each deploy; it creates or updates the user and does nothing if they are unset:

```
DASHBOARD_USER_NAME=Family
DASHBOARD_USER_EMAIL=frame@example.com
DASHBOARD_USER_PASSWORD=...
```

For a frame that stays signed in, use `SESSION_DRIVER=database` and a long `SESSION_LIFETIME` (minutes, for example `525600`). The session cookie is the one credential on the frame, so keep sessions revocable and never put other secrets in client code.

## Settings

Everything can be edited in the browser, so nothing has to live in environment variables. The settings are split into three sections, each its own page in the host app (or all together on the standalone `/dashboard-settings` page):

- **People.** Name, a Gravatar email, and a color for each person.
- **Calendars.** For each read-only iCal feed: name, category (Personal, Class Schedule or Assignments), whose calendar it is, the feed address, and a color. A calendar that belongs to a person uses that person's color; the color picker only appears for calendars with nobody assigned. Add, remove, reorder and test feeds.
- **Location and weather.** Search for your place (Open-Meteo geocoding, no key), or enter coordinates, plus the time zone and units.

How it behaves:

- Saved values live in the `home_dashboard_settings` table, encrypted with `APP_KEY` (rotating the key means re-entering them).
- A saved value wins over the matching environment variable, and with nothing saved the env vars apply, so an env-only setup keeps working. "Use environment defaults" clears everything saved.
- Feed URLs are write-only. They are never sent back to the browser: a saved address shows as "Saved" and can only be replaced.
- Each section saves only its own part. Removing a person unassigns them from any saved calendars.
- Any signed-in user can open the pages. The package leaves access control to the host app; add middleware through `home-dashboard.route.middleware` if you want to restrict them.
- A change made on one device reloads the frame on its own (see Auto-reload).

To embed a section in your own settings area, use the form component:

```blade
<livewire:home-dashboard.settings-form :embedded="true" section="people" />   {{-- people, calendars, location, or omit for all --}}
```

and point the frame's Settings button at your page with the `settings_route` config key (a route name).

## Weather and calendars

All data is fetched server-side and cached. A failed refresh serves the last good copy, so a flaky network or feed never blanks the screen. Cached copies written by an older release are ignored rather than trusted.

Environment fallbacks (all optional once you use the settings pages):

```
DASHBOARD_TIMEZONE=America/Chicago
DASHBOARD_LATITUDE=34.7465
DASHBOARD_LONGITUDE=-92.2896
DASHBOARD_UNITS=imperial            # or metric
DASHBOARD_CALENDARS='[{"name":"Family","color":"sky","url":"https://...","category":"Personal","person":"josh"}]'
DASHBOARD_PEOPLE='[{"id":"josh","name":"Josh","email":"...","color":"rose"}]'
```

- **Weather** comes from [Open-Meteo](https://open-meteo.com/) and needs no API key: current conditions, feels-like, humidity, wind, today's chance of rain, and a five-day forecast with the chance of rain per day.
- **Radar and alerts (US only).** A Radar button on the weather card opens the animated National Weather Service loop for the nearest radar station, found once from your coordinates and cached. Active NWS alerts appear as a banner on Today, most severe first. Outside the US both stay hidden. No key is needed.
- **Calendars** are read-only iCal feed URLs (for example Google Calendar's "Secret address in iCal format" or a school's `webcal://` subscription link). `DASHBOARD_CALENDARS` is a one-line JSON list; wrap it in single quotes, since it contains spaces and double quotes. Entries with a blank or bare-scheme `url` are skipped. The same event on several calendars is shown once, with everyone involved.
- **Feed freshness.** Feeds are cached for 10 minutes and the open page refreshes every 5, so a change shows up within about 15 minutes. Google's own iCal feed can lag far behind its calendar, and nothing here can speed that up.
- **Colors** are `sky`, `amber`, `rose`, `emerald`, `violet`, `orange`, `teal`, `pink`, or any hex value such as `#a3e635`.
- Feed URLs are credentials. They are used only for the server-side fetch and never appear in rendered HTML, logs or cache keys.

Recurring, all-day and multi-day events are expanded with [sabre/vobject](https://github.com/sabre-io/vobject) and bucketed by day in the configured time zone, so the device's own time zone never matters.

### People, categories and filters

- **Pictures.** Events show the picture of each person involved: their [Gravatar](https://gravatar.com), or a colored initial when they have none. The browser receives only a SHA-256 hash of the email, never the address.
- **Categories** (`categories` in the config) group calendars. The first is the default for a new calendar. Today's "Up next" lists only `today_categories` (Personal by default), so class schedules do not crowd it.
- **Filters.** Each calendar tab offers Everyone, Shared (calendars with nobody assigned) and one button per person, but only for choices that actually have a calendar on that tab. A tab with a single choice shows no filter bar.

## Shopping list and meals (Notion)

The Shopping and Meals tabs read and write four Notion databases, so Notion stays the single source of truth and the family can edit from any device:

| Database | Columns |
| --- | --- |
| **Aisles** | Name, Store (Kroger / Costco), Order (walking order; Costco can be its own aisle), Kroger aisle (optional label this row stands for), Description |
| **Items** | Name, Aisle (relation), Need it (checkbox: ticked means it is on the list), Note (for example "need 1") |
| **Meals** | Name, Ingredients (relation to Items), Recipe link, Notes |
| **Meal Plan** | Name, Date (optional), Meal (relation), Added to list (checkbox), Done (checkbox) |

Create an internal integration at <https://www.notion.so/profile/integrations> with read, update and insert content, share the page holding the four databases with it, and configure:

```
NOTION_TOKEN=ntn_...        # the integration secret; server-side only, never in the repo
NOTION_AISLES=...           # data source ids (database settings, or the collection:// url)
NOTION_ITEMS=...
NOTION_MEALS=...
NOTION_PLAN=...
```

- **Shopping** lists every Item with Need it ticked, grouped by the Store of its Aisle (Kroger first; an item with no aisle counts as Kroger), alphabetically. Tapping an item unticks it in Notion. The box at the top adds items: matching known items appear as chips to tap, and the Add button puts a typed name on the list, creating a new Item when it is not already in the catalog. The aisle is for the shopping trip itself, so build a Notion view for that: filter Need it, and sort by a rollup of the Aisle's Order (an "Aisle order" rollup on Items) so the list follows the store's walking order.
- **Meals** shows the plan for Sunday to Saturday and starts over each Sunday, when the groceries are bought. Tap Choose on a day to pick one of the week's undated meals for it; tap a planned meal and Clear this day to put it back. A Meal Plan row with no Date is a meal planned for the week but not for a day: it appears under "Pick one for the week", and its Tonight button gives it today's date. An undated row belongs to the week it was created in (from two days before that Sunday, so Friday and Saturday planning counts). Tick Done on an undated row to hide it.
  "Add ingredients to the list" ticks Need it on every ingredient of the planned meals that is not already on the list and sets Added to list on those plan rows, so pressing it twice does nothing the second time.
- Data is cached for a minute (items) to five minutes (aisles, meals, plan), and a failed refresh serves the last good copy. Changes made in Notion appear within about that time plus the pane's refresh. The token never reaches the browser, and only ids that came from the Items database can be ticked.

### Filing items by Kroger aisle (optional)

Items on the list with no Aisle are filed automatically using [Kroger's public product API](https://developer.kroger.com), which reports the aisle each product is in at a given store. Register an app there (Products), then set:

```
KROGER_CLIENT_ID=...
KROGER_CLIENT_SECRET=...      # server-side only, like the Notion token
KROGER_LOCATION_ID=...        # division + store number: kroger.com/stores/.../025/00622 is 02500622
```

The aisle row is found by its "Kroger aisle" label (for example `AISLE 14` or `DAIRY`) or created on first use, with an Order that follows a walk through the store: produce, deli, bakery, the even aisles up the store (meat after aisle 14), dairy, personal care, pharmacy, then the odd aisles back down from the highest, with anything else last (the order is the rule in `Kroger::order()`, and you can edit any row's Order), and the item's Aisle is written back to Notion, so an aisle you choose yourself is never overwritten. At most five lookups run per refresh, answers are remembered for 30 days (a miss for a day, a failure for five minutes), and nothing breaks when Kroger is unreachable: items just stay under Unsorted. Phrases are tried whole, then without the container ("jar of red sauce" as "red sauce"), then as the first of an either-or ("ground beef or turkey" as "ground beef"). To file a whole catalog at once instead of as items reach the list, run `php artisan home-dashboard:file-items`; Notion writes are retried when it rate-limits.

## Background pictures

Every tab except Settings shows a new picture every hour under a dark wash, with translucent cards so a little of it shows through. On the idle screen the same picture appears dimmed behind the clock, with its title and credit in a corner box. The ⓘ button on Today opens a lightbox with the title, artist, date, a short description when there is one, and the credit.

- **Art Institute of Chicago** (no key): public-domain paintings from the museum's open API. The search is a strict allow-list: landscapes and seascapes only, and anything mentioning people or figures, nudity, bathing, death, violence or war is excluded. The result is deterministic per hour, cached, and shaped for a landscape frame. Descriptions are CC BY 4.0 and are credited in the lightbox.
- **NASA Astronomy Picture of the Day** (optional): set `DASHBOARD_NASA_KEY` (a free key from <https://api.nasa.gov>) and NASA takes every other hour. APOD is one picture per day, so it stays the same until midnight; videos and copyrighted pictures are skipped. Treat the key as a secret: set it as a host environment variable. It is only used server-side and never reaches the browser.
- Turn the feature off with `DASHBOARD_BACKGROUND=false`. If a source is unreachable the plain background shows instead.

The browser loads images directly from `artic.edu`, `apod.nasa.gov`, `gravatar.com` and `radar.weather.gov`, so those hosts need to be reachable from the frame.

## Auto-reload

A kiosk never refreshes on its own, so the shell asks an authenticated `/version` endpoint every 120 s and reloads itself, only while nobody has touched the screen for 30 s, when the answer changes. It reloads after a deploy (the version is derived from the built asset manifest, `composer.lock` and the modified time of `bootstrap/app.php`) and after any change to the saved settings (a short fingerprint of the settings, revealing nothing about their values). Set `DASHBOARD_VERSION` to control the deploy version yourself, and `DASHBOARD_VERSION_CHECK` to change the interval in seconds (`0` disables the check). A frame that loaded its page before an upgrade needs one manual refresh first.

## Configuration

`config/home-dashboard.php` (publish it with `php artisan vendor:publish --tag=home-dashboard-config`) covers the route and middleware, `settings_route`, idle and ambient timers, location and data sources, calendar `categories` and `today_categories`, the `background` options, cache lifetimes, the `notion` token and database ids, the tab list, the Vite entry points and the user env mapping. The views can be published with `--tag=home-dashboard-views`.

If you publish the config, remember Laravel merges package config one level deep: overriding an array such as `route`, `tabs` or `background` replaces all of it, so include every key you need.

## Testing

```bash
composer install
vendor/bin/phpunit
```

The suite uses Orchestra Testbench with Livewire and Flux. Outbound requests are faked, and the background picture is off by default in tests.

## License

MIT. See [LICENSE](LICENSE).

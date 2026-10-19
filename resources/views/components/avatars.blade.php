@props(['people' => [], 'swatch' => '#a1a1aa'])
{{--
    Who an event belongs to. Each avatar is a colored initial with the person's Gravatar laid over
    it: if there is no Gravatar (or the frame is offline) the image removes itself and the initial shows.
    Events with nobody assigned keep the plain color marker.
--}}
@if ($people)
    <div class="flex shrink-0 -space-x-2" aria-label="{{ collect($people)->pluck('name')->implode(', ') }}">
        @foreach (array_slice($people, 0, 3) as $person)
            <span class="relative inline-flex size-9 items-center justify-center rounded-full text-[0.9rem] font-semibold text-zinc-900 ring-2 ring-zinc-900" style="background: {{ $person['color'] }}" title="{{ $person['name'] }}">
                {{ $person['initial'] }}
                @if ($person['avatar'])
                    <img src="{{ $person['avatar'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()" class="absolute inset-0 size-full rounded-full object-cover">
                @endif
            </span>
        @endforeach
        @if (count($people) > 3)
            <span class="relative inline-flex size-9 items-center justify-center rounded-full bg-zinc-700 text-[0.8rem] font-semibold text-zinc-100 ring-2 ring-zinc-900">+{{ count($people) - 3 }}</span>
        @endif
    </div>
@else
    <span class="mt-[0.45rem] size-3 shrink-0 rounded-full" style="background: {{ $swatch }}"></span>
@endif

<?php

namespace JoshDaugherty\HomeDashboard\Support;

use Illuminate\Support\Str;

/**
 * The household members calendars can belong to. A person's email is used only to
 * build a Gravatar URL (a hash), so the address itself is never sent to the browser
 * on the dashboard.
 */
class People
{
    /**
     * @return list<array{id: string, name: string, email: string|null}>
     */
    public function all(): array
    {
        $raw = app(Settings::class)->get('people');

        if (is_string($raw)) {
            $raw = trim($raw) === '' ? [] : json_decode($raw, true);
        }

        if (! is_array($raw)) {
            return [];
        }

        $people = [];

        foreach (array_values($raw) as $i => $item) {
            $name = is_array($item) ? trim((string) ($item['name'] ?? '')) : '';

            if ($name === '') {
                continue;
            }

            $email = strtolower(trim((string) ($item['email'] ?? '')));

            $people[] = [
                'id' => (string) ($item['id'] ?? (Str::slug($name) ?: 'p'.$i)),
                'name' => $name,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            ];
        }

        return $people;
    }

    public static function gravatar(?string $email, int $size = 128): ?string
    {
        if (! $email) {
            return null;
        }

        // d=404: no Gravatar means no image, so the page falls back to the initial.
        return 'https://gravatar.com/avatar/'.hash('sha256', strtolower(trim($email))).'?s='.$size.'&d=404';
    }

    /**
     * Avatar data for the people an event belongs to.
     *
     * @param  array<string, string>  $colorsById  person id => color of the calendar they came from
     * @return list<array{id: string, name: string, initial: string, avatar: string|null, color: string}>
     */
    public function resolve(array $colorsById): array
    {
        if ($colorsById === []) {
            return [];
        }

        $known = collect($this->all())->keyBy('id');
        $out = [];

        foreach ($colorsById as $id => $color) {
            if (! $person = $known->get($id)) {
                continue;
            }

            $out[] = [
                'id' => $person['id'],
                'name' => $person['name'],
                'initial' => mb_strtoupper(mb_substr($person['name'], 0, 1)),
                'avatar' => self::gravatar($person['email']),
                'color' => $color,
            ];
        }

        return $out;
    }
}

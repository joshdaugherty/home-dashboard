<?php

namespace JoshDaugherty\HomeDashboard\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A very small Notion API client: query a data source, update a page. The token is an
 * internal-integration secret and is only ever used server-side.
 */
class Notion
{
    private const VERSION = '2025-09-03';

    public function token(): ?string
    {
        $token = config('home-dashboard.notion.token');

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    public function dataSource(string $name): ?string
    {
        $id = config("home-dashboard.notion.{$name}");

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    public function configured(): bool
    {
        return $this->token() !== null
            && collect(['aisles', 'items', 'meals', 'plan'])->every(fn ($name) => $this->dataSource($name) !== null);
    }

    /** Every page in a data source matching the optional filter and sorts. */
    public function query(string $dataSourceId, array $body = []): array
    {
        $pages = [];
        $cursor = null;

        for ($i = 0; $i < 10; $i++) {
            $response = $this->request()->post("https://api.notion.com/v1/data_sources/{$dataSourceId}/query", array_filter([
                ...$body,
                'page_size' => 100,
                'start_cursor' => $cursor,
            ]));

            if (! $response->successful()) {
                throw new RuntimeException('Notion query failed ('.$response->status().').');
            }

            $pages = [...$pages, ...($response->json('results') ?? [])];

            if (! $response->json('has_more') || ! ($cursor = $response->json('next_cursor'))) {
                break;
            }
        }

        return $pages;
    }

    public function update(string $pageId, array $properties): void
    {
        $response = $this->request()->patch("https://api.notion.com/v1/pages/{$pageId}", ['properties' => $properties]);

        if (! $response->successful()) {
            throw new RuntimeException('Notion update failed ('.$response->status().').');
        }
    }

    private function request()
    {
        return Http::withToken((string) $this->token())
            ->withHeaders(['Notion-Version' => self::VERSION])
            ->acceptJson()
            ->asJson()
            ->timeout(10);
    }
}

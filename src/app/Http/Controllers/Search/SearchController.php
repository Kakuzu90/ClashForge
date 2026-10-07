<?php

namespace App\Http\Controllers\Search;

use App\Domain\Bases\Data\BaseCardData;
use App\Domain\Bases\Data\FeedOptionsData;
use App\Domain\PlayerAccounts\Data\PlayerCardData;
use App\Domain\Search\Contracts\SearchService;
use App\Domain\Search\Data\ParsedFilterData;
use App\Domain\Search\Data\SearchResultsData;
use App\Domain\Search\Enums\SearchType;
use App\Domain\Users\Data\PlayerHitData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchRequest;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/search` (FR-SEARCH-1..3, P3-05): one input, results grouped by kind or one kind with "Load
 * more". A player tag that matches a listed account goes straight to its page (FR-SEARCH-2).
 * `bases`, `players` and `accounts` are merge props: "Load more" reloads one of them with the
 * next cursor and Inertia appends it. The search runs once per request. Never indexed.
 */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, SearchService $search): Response|RedirectResponse
    {
        $results = $search->search($request->toQuery(), $request->user());

        if ($results->accountUlid !== null) {
            return redirect()->route('accounts.show', $results->accountUlid);
        }

        $items = fn (SearchType $type): array => array_map(
            fn (BaseCardData|PlayerHitData|PlayerCardData $item): array => $item->toArray(),
            $results->section($type)->items ?? [],
        );

        return PageMeta::page('Search/Index', [
            'q' => $results->term,
            'type' => $results->type->value,
            'filters' => $results->filters->toArray(),
            'parsed' => array_map(fn (ParsedFilterData $chip): array => $chip->toArray(), $results->parsed),
            'tag' => $results->tag,
            'searched' => array_keys($results->sections),
            'more' => $this->more($results),
            'options' => fn (): array => FeedOptionsData::for($request->sorts())->toArray(),
            'facets' => fn (): ?array => $results->section(SearchType::Bases)?->facets?->toArray(),
            'bases' => Inertia::merge(fn (): array => $items(SearchType::Bases))->matchOn('ulid'),
            'players' => Inertia::merge(fn (): array => $items(SearchType::Players))->matchOn('username'),
            'accounts' => Inertia::merge(fn (): array => $items(SearchType::Accounts))->matchOn('ulid'),
            'nextCursor' => fn (): ?string => $results->type === SearchType::All ? null : $results->section($results->type)?->nextCursor,
        ], new PageMeta(
            title: $results->term === '' ? 'Search' : "Search: {$results->term}",
            description: 'Search Clash of Clans base layouts, players and verified accounts on Clash Commons.',
            noindex: true,
        ));
    }

    /**
     * @return array{bases: bool, players: bool, accounts: bool}
     */
    private function more(SearchResultsData $results): array
    {
        return [
            'bases' => $results->section(SearchType::Bases)->hasMore ?? false,
            'players' => $results->section(SearchType::Players)->hasMore ?? false,
            'accounts' => $results->section(SearchType::Accounts)->hasMore ?? false,
        ];
    }
}

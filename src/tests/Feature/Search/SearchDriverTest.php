<?php

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\FeedSort;
use App\Domain\Bases\Services\BaseSearchSource;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Services\AccountSearchSource;
use App\Domain\Search\Contracts\SearchService;
use App\Domain\Search\Contracts\SearchSource;
use App\Domain\Search\Contracts\TagLookup;
use App\Domain\Search\Data\SearchCursor;
use App\Domain\Search\Data\SearchQuery;
use App\Domain\Search\Data\SearchSectionData;
use App\Domain\Search\Data\SourceQuery;
use App\Domain\Search\Enums\SearchType;
use App\Domain\Users\Services\PlayerSearchSource;
use App\Models\User;
use Carbon\CarbonImmutable;

// P3-05: what the driver hands each source (specs/17 §1, §3): parsed filters merged under explicit
// ones, the signature that binds cursors and cache entries, and the per-kind limits. The sources
// are replaced by recorders, so this runs on any database.

/**
 * What each kind was asked, by type.
 *
 * @return ArrayObject<string, list<SourceQuery>>
 */
function recordSources(): ArrayObject
{
    $asked = new ArrayObject;

    app()->instance(TagLookup::class, new class implements TagLookup
    {
        public function findAccount(PlayerTag $tag, ?User $viewer): ?string
        {
            return null;
        }
    });

    foreach ([BaseSearchSource::class => SearchType::Bases, PlayerSearchSource::class => SearchType::Players, AccountSearchSource::class => SearchType::Accounts] as $class => $type) {
        app()->instance($class, new class($type, $asked) implements SearchSource
        {
            public function __construct(private SearchType $kind, private ArrayObject $asked) {}

            public function type(): SearchType
            {
                return $this->kind;
            }

            public function search(SourceQuery $query, ?User $viewer): SearchSectionData
            {
                $this->asked[$this->kind->value] = [...($this->asked[$this->kind->value] ?? []), $query];

                return new SearchSectionData($this->kind, [], false, null);
            }

            public function reindex(): int
            {
                return 1;
            }
        });
    }

    return $asked;
}

function driverSearch(string $term, SearchType $type = SearchType::All, FeedFiltersData $filters = new FeedFiltersData(sort: FeedSort::Relevance), ?string $cursor = null): void
{
    app(SearchService::class)->search(new SearchQuery($term, $type, $filters, $cursor), User::factory()->create());
}

it('asks each kind for a few hits on a grouped search, and a page on a single kind', function () {
    $asked = recordSources();

    driverSearch('ring');
    driverSearch('ring', SearchType::Players);

    expect($asked['bases'][0]->limit)->toBe(config('platform.search.group_size.bases'))
        ->and($asked['bases'][0]->paged)->toBeFalse()
        ->and($asked['bases'][0]->facets)->toBeFalse()
        ->and($asked['players'][1]->limit)->toBe(config('platform.search.per_page'))
        ->and($asked['players'][1]->paged)->toBeTrue()
        ->and($asked)->toHaveCount(3);
});

it('fills unset filters from the text and keeps the explicit ones', function () {
    $asked = recordSources();

    driverSearch('TH17 war ring', SearchType::Bases);
    driverSearch('TH17 war ring', SearchType::Bases, new FeedFiltersData(thMin: 15, thMax: 15, sort: FeedSort::Relevance));

    [$parsed, $explicit] = $asked['bases'];

    expect($parsed->term)->toBe('ring')
        ->and([$parsed->filters->thMin, $parsed->filters->thMax, $parsed->filters->category])->toBe([17, 17, BaseCategory::War])
        ->and($parsed->facets)->toBeTrue()
        ->and([$explicit->filters->thMin, $explicit->filters->category])->toBe([15, BaseCategory::War])
        // The text still named a Town Hall: accounts would filter on it.
        ->and($explicit->thLevel)->toBe(17);
});

it('signs each search apart, so a cursor or cache entry never crosses to another', function () {
    $asked = recordSources();

    driverSearch('ring', SearchType::Bases);
    driverSearch('Ring', SearchType::Bases);
    driverSearch('ring box', SearchType::Bases);
    driverSearch('ring th16', SearchType::Bases);
    driverSearch('ring', SearchType::Bases, new FeedFiltersData(sort: FeedSort::Newest));
    driverSearch('ring', SearchType::Players);

    $signatures = array_map(fn (SourceQuery $q): string => $q->signature, [...$asked['bases'], ...$asked['players']]);

    // Case does not matter; everything else does.
    expect($signatures[0])->toBe($signatures[1])
        ->and(array_unique(array_slice($signatures, 1)))->toHaveCount(5);
});

it('fixes the ranking time from the cursor for later pages', function () {
    $asked = recordSources();
    driverSearch('ring', SearchType::Bases);
    $signature = $asked['bases'][0]->signature;
    $at = CarbonImmutable::parse('2026-10-01 08:00:00');

    driverSearch('ring', SearchType::Bases, cursor: SearchCursor::for($signature, ['0.5'], 9, 2, $at));

    expect($asked['bases'][1]->now->getTimestamp())->toBe($at->getTimestamp())
        ->and($asked['bases'][1]->cursor?->id)->toBe(9);
});

it('asks no source anything for empty text or a tag', function () {
    $asked = recordSources();

    driverSearch('');
    driverSearch('#2PPQ');

    expect($asked)->toHaveCount(0);
});

it('reindexes one kind or all of them', function () {
    recordSources();

    expect(app(SearchService::class)->reindex(SearchType::Bases))->toBe(1)
        ->and(app(SearchService::class)->reindex())->toBe(3);
});

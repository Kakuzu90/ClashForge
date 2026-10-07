<?php

namespace App\Http\Controllers\Bases;

use App\Domain\Bases\Data\BaseCardData;
use App\Domain\Bases\Data\FeedOptionsData;
use App\Domain\Bases\Data\FeedPageData;
use App\Domain\Bases\Queries\BaseFeedQuery;
use App\Domain\Bases\Services\FeedDefaults;
use App\Http\Requests\Bases\BaseFeedRequest;
use Inertia\Inertia;

/**
 * The props a feed page shares (`/` and `/bases`, P3-03). `cards` is a merge prop: "Load more"
 * reloads only `cards` and `nextCursor` with the next cursor, and Inertia appends the page. The
 * feed runs once per request whichever props are asked for.
 */
final class FeedProps
{
    /**
     * @return array<string, mixed>
     */
    public static function for(BaseFeedRequest $request, BaseFeedQuery $feed, FeedDefaults $defaults, bool $defaultTh): array
    {
        $viewer = $request->user();
        $filters = $request->toData();
        $fromAccount = false;

        if ($defaultTh && $request->wantsDefaultTh() && $request->feedCursor() === null) {
            $withDefault = $defaults->forViewer($viewer, $filters);
            $fromAccount = $withDefault->thMin !== $filters->thMin;
            $filters = $withDefault;
        }

        $page = null;
        $load = function () use (&$page, $feed, $filters, $request, $viewer): FeedPageData {
            return $page ??= $feed->page($filters, $request->feedCursor(), $viewer === null);
        };

        return [
            'filters' => $filters->toArray(),
            'thFromAccount' => $fromAccount,
            'options' => fn (): array => FeedOptionsData::for($request->sorts())->toArray(),
            'cards' => Inertia::merge(fn (): array => array_map(fn (BaseCardData $card): array => $card->toArray(), $load()->cards))->matchOn('ulid'),
            'nextCursor' => fn (): ?string => $load()->nextCursor,
        ];
    }
}

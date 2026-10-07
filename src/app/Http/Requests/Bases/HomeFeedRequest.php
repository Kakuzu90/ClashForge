<?php

namespace App\Http\Requests\Bases;

use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Enums\FeedSort;

/**
 * The home feed (specs/18 §6): the Town Hall chips and the Trending / New / Most copied tabs only;
 * every other filter lives on `/bases`. Unknown parameters are ignored.
 */
class HomeFeedRequest extends BaseFeedRequest
{
    /**
     * @var string
     */
    protected $redirectRoute = 'home';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_intersect_key(parent::rules(), array_flip(['th', 'sort', 'cursor']));
    }

    /**
     * Built from the validated `th` and `sort` only: the other filters are not validated here, so
     * they must never be read (a bad `category` would otherwise throw).
     */
    public function toData(): FeedFiltersData
    {
        [$min, $max] = $this->thBounds();

        return new FeedFiltersData($min, $max, sort: $this->sortOrDefault());
    }

    /**
     * @return list<FeedSort>
     */
    public function sorts(): array
    {
        return [FeedSort::Trending, FeedSort::Newest, FeedSort::MostCopied];
    }
}

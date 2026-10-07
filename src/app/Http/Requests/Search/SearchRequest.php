<?php

namespace App\Http\Requests\Search;

use App\Domain\Bases\Enums\FeedSort;
use App\Domain\Search\Contracts\SearchService;
use App\Domain\Search\Data\SearchQuery;
use App\Domain\Search\Enums\SearchType;
use App\Http\Requests\Bases\BaseFeedRequest;
use App\Support\Rules\Utf8Text;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `/search` (specs/17, P3-05): the text `q` (2 to 100 characters, specs/17 §4), the result `type`,
 * and on the Bases tab every `/bases` filter plus "Best match". Public, read only. A bad value
 * redirects to the search with the error, keeping the text when the text itself is fine.
 */
class SearchRequest extends BaseFeedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'q' => ['nullable', 'string', 'min:'.(int) config('platform.search.min_term'), 'max:'.(int) config('platform.search.max_term'), new Utf8Text],
            'type' => ['nullable', Rule::enum(SearchType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.min' => 'Type at least :min characters to search.',
            'q.max' => 'Keep the search under :max characters.',
        ];
    }

    /**
     * The text must give the search something to look for, and the cursor must belong to it.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->filled('q') && ! app(SearchService::class)->hasSearchableText($this->toQuery())) {
                    $validator->errors()->add('q', 'Add a word to search for, not only words to leave out.');
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->feedCursor() !== null && ! app(SearchService::class)->acceptsCursor($this->toQuery(), $this->user() === null)) {
                    $validator->errors()->add('cursor', 'That page link is not valid. Start from the first page.');
                }
            },
        ];
    }

    public function toQuery(): SearchQuery
    {
        return new SearchQuery(
            term: $this->string('q')->trim()->toString(),
            type: SearchType::tryFrom($this->string('type')->toString()) ?? SearchType::All,
            filters: $this->toData(),
            cursor: $this->feedCursor(),
        );
    }

    /**
     * @return list<FeedSort>
     */
    public function sorts(): array
    {
        return [FeedSort::Relevance, ...FeedSort::feed()];
    }

    protected function sortOrDefault(): FeedSort
    {
        return FeedSort::tryFrom($this->string('sort')->toString()) ?? FeedSort::Relevance;
    }

    /**
     * Back to the search, keeping the text unless the text was the problem, so the redirect never
     * fails again on the same input.
     */
    protected function getRedirectUrl(): string
    {
        $q = $this->query('q');
        $keep = is_string($q) && ! $this->getValidatorInstance()->errors()->has('q');

        return route('search', $keep ? ['q' => $q] : []);
    }
}

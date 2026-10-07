<?php

namespace App\Http\Requests\Bases;

use App\Domain\Bases\Data\BaseFieldRules;
use App\Domain\Bases\Data\FeedFiltersData;
use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\FeedSort;
use App\Domain\Bases\Queries\BaseFeedQuery;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The `/bases` feed's query string (FR-BASE-13): `th` (a level, a range `15-17`, or `all`),
 * `category`, `tag`, `min_likes`, `video`, `sort` and the `cursor` of the next page. Public, read
 * only. A bad value redirects to the unfiltered feed with the error.
 */
class BaseFeedRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $redirectRoute = 'bases.index';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'th' => ['nullable', 'string', 'max:5', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && $value !== 'all' && self::thRange($value) === null) {
                    $fail('Pick a Town Hall level from '.config('bases.th_min').' to '.config('bases.th_max').'.');
                }
            }],
            'category' => ['nullable', Rule::enum(BaseCategory::class)],
            'tag' => ['nullable', ...BaseFieldRules::tag()],
            'min_likes' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('bases.feed.min_likes_max')],
            'video' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_map(fn (FeedSort $sort): string => $sort->value, $this->sorts()))],
            'cursor' => ['nullable', 'string', 'max:1024'],
        ];
    }

    /**
     * The cursor must belong to the feed these filters describe.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $cursor = $this->query('cursor');

                if ($validator->errors()->isEmpty() && is_string($cursor) && ! BaseFeedQuery::acceptsCursor($cursor, $this->toData(), $this->user() === null)) {
                    $validator->errors()->add('cursor', 'That page link is not valid. Start from the first page.');
                }
            },
        ];
    }

    /**
     * No `th` at all, as opposed to `th=all`: a signed-in home feed then starts at the viewer's
     * Town Hall.
     */
    public function wantsDefaultTh(): bool
    {
        return ! $this->filled('th');
    }

    public function toData(): FeedFiltersData
    {
        [$min, $max] = $this->thBounds();
        $tag = $this->string('tag')->toString();

        return new FeedFiltersData(
            thMin: $min,
            thMax: $max,
            category: $this->filled('category') ? BaseCategory::from($this->string('category')->toString()) : null,
            tag: $tag === '' ? null : BaseFieldRules::normaliseTag($tag),
            minLikes: $this->filled('min_likes') ? $this->integer('min_likes') : null,
            hasVideo: $this->boolean('video'),
            sort: $this->sortOrDefault(),
        );
    }

    /**
     * @return array{int|null, int|null}
     */
    protected function thBounds(): array
    {
        $range = $this->filled('th') ? self::thRange($this->string('th')->toString()) : null;

        return [$range[0] ?? null, $range[1] ?? null];
    }

    protected function sortOrDefault(): FeedSort
    {
        return FeedSort::tryFrom($this->string('sort')->toString()) ?? FeedSort::Trending;
    }

    public function feedCursor(): ?string
    {
        $cursor = $this->query('cursor');

        return is_string($cursor) ? $cursor : null;
    }

    /**
     * @return list<FeedSort>
     */
    public function sorts(): array
    {
        return FeedSort::feed();
    }

    /**
     * `16` or `15-17`, inside `bases.th_min`–`th_max`, low to high.
     *
     * @return array{int, int}|null
     */
    private static function thRange(string $value): ?array
    {
        if (! preg_match('/^(\d{1,2})(?:-(\d{1,2}))?$/', $value, $m)) {
            return null;
        }

        $min = (int) $m[1];
        $max = isset($m[2]) ? (int) $m[2] : $min;

        return $min <= $max && $min >= (int) config('bases.th_min') && $max <= (int) config('bases.th_max') ? [$min, $max] : null;
    }
}

<?php

namespace App\Domain\Search\Services;

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Search\Data\ParsedFilterData;
use App\Domain\Search\Data\ParsedQuery;

/**
 * Reads structure out of the search text (specs/17 §3): a player tag on its own, a Town Hall
 * (`TH17`, `th 17`, `Town Hall 17`) and a base category by name or synonym. What it reads is
 * returned as filters for the page to show as chips; the rest stays as the text to match. Once a
 * filter is found, words that only say "base" are dropped, so `TH17 war base` does not require
 * "base" in the title.
 */
class QueryParser
{
    /**
     * Synonyms by category, matched case-insensitively with spaces, hyphens or underscores (or
     * nothing) between words. Longer phrases win.
     *
     * @var array<string, list<string>>
     */
    private const SYNONYMS = [
        'war' => ['war', 'cw'],
        'cwl' => ['cwl', 'clan war league', 'league war'],
        'farming' => ['farming', 'farm'],
        'trophy' => ['trophy', 'trophies', 'trophy push', 'pushing'],
        'legend' => ['legend', 'legends', 'legend league'],
        'anti_3_star' => ['anti 3 star', 'anti three star', 'anti 3'],
        'anti_2_star' => ['anti 2 star', 'anti two star', 'anti 2'],
        'hybrid' => ['hybrid'],
        'progress' => ['progress', 'progression'],
        'troll' => ['troll', 'funny'],
    ];

    /**
     * Words that only say "a base" once a filter already says which.
     *
     * @var list<string>
     */
    private const FILLER = ['base', 'bases', 'layout', 'layouts', 'link', 'links'];

    public function parse(string $text): ParsedQuery
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (($tag = $this->tag($text)) !== null) {
            return new ParsedQuery('', tag: $tag);
        }

        $filters = [];
        $th = null;
        $category = null;

        if (preg_match('/(?<![\p{L}\p{N}])(?:th|town\s*hall)\s*-?\s*(\d{1,2})(?![\p{L}\p{N}])/iu', $text, $m) === 1) {
            $level = (int) $m[1];

            if ($level >= (int) config('bases.th_min') && $level <= (int) config('bases.th_max')) {
                $th = $level;
                $filters[] = new ParsedFilterData('th', (string) $level, "Town Hall {$level}", $m[0]);
                $text = $this->remove($text, $m[0]);
            }
        }

        foreach ($this->phrases() as [$phrase, $value]) {
            $pattern = '/(?<![\p{L}\p{N}])'.implode('[\s\-_]*', array_map(fn (string $word): string => preg_quote($word, '/'), explode(' ', $phrase))).'(?![\p{L}\p{N}])/iu';

            if (preg_match($pattern, $text, $m) === 1) {
                $category = BaseCategory::from($value);
                $filters[] = new ParsedFilterData('category', $value, $category->label(), $m[0]);
                $text = $this->remove($text, $m[0]);
                break;
            }
        }

        if ($filters !== []) {
            $words = array_filter(explode(' ', $text), fn (string $word): bool => ! in_array(mb_strtolower($word), self::FILLER, true));
            $text = implode(' ', $words);
        }

        return new ParsedQuery(trim($text), $th, $category, $filters);
    }

    /**
     * `#` and anything that makes a valid tag, or the bare tag in capitals (specs/17 §3:
     * `^#?[0289PYLQGRJCUV]{3,12}$`). Lowercase words without a `#` are text: "pug" is a word.
     */
    private function tag(string $text): ?PlayerTag
    {
        if (str_starts_with($text, '#')) {
            return PlayerTag::tryFrom($text);
        }

        return preg_match('/^['.PlayerTag::ALPHABET.']{'.PlayerTag::MIN.','.PlayerTag::MAX.'}$/D', $text) === 1 ? PlayerTag::tryFrom($text) : null;
    }

    private function remove(string $text, string $match): string
    {
        $at = mb_strpos($text, $match);

        return $at === false ? $text : trim((string) preg_replace('/\s+/u', ' ', mb_substr($text, 0, $at).' '.mb_substr($text, $at + mb_strlen($match))));
    }

    /**
     * Every synonym with its category, longest first.
     *
     * @return list<array{string, string}>
     */
    private function phrases(): array
    {
        $phrases = [];
        foreach (self::SYNONYMS as $value => $synonyms) {
            foreach ($synonyms as $phrase) {
                $phrases[] = [$phrase, $value];
            }
        }

        usort($phrases, fn (array $a, array $b): int => mb_strlen($b[0]) <=> mb_strlen($a[0]));

        return $phrases;
    }
}

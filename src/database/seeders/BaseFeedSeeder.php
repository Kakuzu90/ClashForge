<?php

namespace Database\Seeders;

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Models\BaseTag;
use App\Domain\Bases\Services\TrendingService;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Local feed data (P3-03): six authors with verified accounts, one with a private profile, and 60
 * published bases across Town Halls, categories, tags and counters, two of them the same layout.
 * Scores trending at the end. Runs once; local and testing only.
 *
 *   php artisan db:seed --class=BaseFeedSeeder
 */
class BaseFeedSeeder extends Seeder
{
    private const TITLES = [
        'Anti-root ring', 'Compact war box', 'Island core', 'Legend push', 'Anti-dragon spread',
        'Farming dark elixir', 'CWL anti-3', 'Hybrid trap maze', 'Troll funnel', 'Progress layout',
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing') || User::query()->where('username', 'seed_author_1')->exists()) {
            return;
        }

        // All or nothing: a failed run leaves no half-seeded authors behind to block the next one.
        DB::transaction(fn () => $this->seed());

        app(TrendingService::class)->recompute(all: true);
    }

    private function seed(): void
    {
        $authors = [];
        foreach (range(1, 6) as $n) {
            $author = User::factory()
                ->withProfileData(['display_name' => "Seed Author {$n}"])
                ->withPrivacy(['profile_visibility' => $n === 6 ? ProfileVisibility::Private : ProfileVisibility::Public])
                ->create(['username' => "seed_author_{$n}", 'email' => "seed_author_{$n}@example.com", 'verified_accounts_count' => 1]);
            $authors[] = [$author, CocAccount::factory()->for($author)->verified()->featured()->create(['ign' => "Chief {$n}", 'th_level' => 12 + $n])];
        }

        // test_user gets a TH 16 account, so the signed-in home starts at TH 15-17.
        $viewer = User::query()->where('username', 'test_user')->first();
        if ($viewer !== null && ! CocAccount::query()->where('user_id', $viewer->id)->exists()) {
            CocAccount::factory()->for($viewer)->verified()->featured()->create(['ign' => 'Test Chief', 'th_level' => 16]);
            $viewer->forceFill(['verified_accounts_count' => 1])->save();
        }

        $tags = collect((array) config('bases.suggested_tags'))
            ->map(fn (string $name): BaseTag => BaseTag::query()->firstOrCreate(['name' => $name], ['slug' => $name, 'is_suggested' => true]));
        $categories = BaseCategory::cases();
        $now = Date::now();

        foreach (range(1, 60) as $i) {
            [$author, $account] = $authors[$i % count($authors)];
            $th = (int) config('bases.th_max') - ($i % 11);

            $base = BaseLayout::factory()
                ->when($i <= 2, fn ($factory) => $factory->forLayout('TH16:WB:SEEDDUPLICATE'))
                ->withMetrics([
                    'likes_count' => ($i * 37) % 400,
                    'copies_count' => ($i * 13) % 120,
                    'comments_count' => $i % 9,
                    'views_count' => ($i * 211) % 5000,
                ])
                ->create([
                    'user_id' => $author->id,
                    'coc_account_id' => $account->id,
                    'title' => self::TITLES[$i % count(self::TITLES)]." {$i}",
                    'th_level' => $th,
                    'category' => $categories[$i % count($categories)],
                    'has_video' => $i % 5 === 0,
                    'published_at' => $now->subHours($i * 4),
                ]);

            $picked = $tags->slice($i % 7, 2)->map(fn (BaseTag $tag): int => $tag->id)->values()->all();
            $base->tags()->attach($picked);
            BaseTag::query()->whereKey($picked)->increment('usage_count');
        }
    }
}

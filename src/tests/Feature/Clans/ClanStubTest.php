<?php

use App\Domain\Clans\Models\Clan;
use App\Domain\Clans\Services\ClanDirectory;
use App\Domain\Clans\Services\ClanReadModel;
use App\Domain\CocIntegration\Data\ClanTag;
use App\Domain\CocIntegration\Data\PlayerClanData;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Models\SyncState;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Services\AccountSyncService;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Tests\Support\Coc\InteractsWithCoc;

// specs/07 `clans` (read-only stub) and `coc_accounts.clan_id`, P2-13.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->user = User::factory()->create();
    $this->attach = fn (string $tag): CocAccount => CocAccount::query()->where('ulid', app(AttachAccountService::class)->attach($this->user, PlayerTag::from($tag))->accountUlid)->sole();
});

/**
 * The clan fixture player, edited, served by the fake from now on.
 *
 * @param  callable(array<string, mixed>): array<string, mixed>  $edit
 */
function servePlayer(callable $edit): void
{
    test()->fakeCoc()->withPlayer($edit(test()->cocFixture('players/2PQ8GRJC.json')));
}

it('creates the clan stub on attach and links the account (FR-COC-3)', function () {
    $account = ($this->attach)('#2PQ8GRJC');

    $clan = Clan::query()->sole();
    // jsonb does not keep object key order, so JSON columns compare with toEqual.
    expect($account->clan_id)->toBe($clan->id)
        ->and($account->clan_tag)->toBe('#2Q8URJ9L')
        ->and($clan)->tag->toBe('#2Q8URJ9L')->tag_normalized->toBe('2Q8URJ9L')->name->toBe('Fixture Clan')->level->toBe(22)
        ->and($clan->badge_urls)->toEqual([
            'small' => 'https://api-assets.clashofclans.com/badges/70/fixture.png',
            'medium' => 'https://api-assets.clashofclans.com/badges/200/fixture.png',
            'large' => 'https://api-assets.clashofclans.com/badges/512/fixture.png',
        ])
        // The rest waits for the clan sync (P4-01).
        ->and($clan)->members_count->toBeNull()->type->toBeNull()->tracked_reason->toBeNull()->api_synced_at->toBeNull();
});

it('shares one clan row between accounts in the same clan', function () {
    servePlayer(fn (array $p): array => [...$p, 'tag' => '#PQ8GRJC2', 'name' => 'Second Chief']);

    $first = ($this->attach)('#2PQ8GRJC');
    $second = ($this->attach)('#PQ8GRJC2');

    expect(Clan::query()->count())->toBe(1)
        ->and($second->clan_id)->toBe($first->clan_id);
});

it('leaves an account outside a clan unlinked, with no clan row', function () {
    $account = ($this->attach)('#YC8V2QG9');

    expect($account->clan_id)->toBeNull()
        ->and($account->clan_tag)->toBeNull()
        ->and(Clan::query()->count())->toBe(0);
});

describe('on sync', function () {
    beforeEach(function () {
        $this->account = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->verified()->create();
        SyncState::factory()->forAccount($this->account->id)->create();
        $this->sync = fn () => app(AccountSyncService::class)->sync($this->account->id);
    });

    it('links the account to its clan', function () {
        ($this->sync)();

        expect($this->account->fresh()->clan_id)->toBe(Clan::query()->sole()->id);
    });

    it('relinks an account that moved clans, keeping the old clan', function () {
        ($this->sync)();
        $old = Clan::query()->sole();
        servePlayer(fn (array $p): array => [...$p, 'clan' => [...$p['clan'], 'tag' => '#9QJCUGRV', 'name' => 'New Home']]);

        ($this->sync)();

        $new = Clan::query()->where('tag_normalized', '9QJCUGRV')->sole();
        expect($this->account->fresh())->clan_id->toBe($new->id)->clan_tag->toBe('#9QJCUGRV')
            ->and($new->name)->toBe('New Home')
            ->and($old->fresh())->not->toBeNull();
    });

    it('unlinks an account that left its clan', function () {
        ($this->sync)();
        servePlayer(function (array $p): array {
            unset($p['clan'], $p['role']);

            return $p;
        });

        ($this->sync)();

        expect($this->account->fresh())->clan_id->toBeNull()->clan_tag->toBeNull()
            ->and(Clan::query()->count())->toBe(1);
    });

    it('updates the stub when the clan renamed or changed its badge', function () {
        ($this->sync)();
        servePlayer(fn (array $p): array => [...$p, 'clan' => [...$p['clan'], 'name' => 'Renamed Clan', 'clanLevel' => 23, 'badgeUrls' => ['small' => 'https://api-assets.clashofclans.com/badges/70/new.png']]]);

        ($this->sync)();

        expect(Clan::query()->sole())->name->toBe('Renamed Clan')->level->toBe(23)
            ->and(Clan::query()->sole()->badge_urls)->toEqual(['small' => 'https://api-assets.clashofclans.com/badges/70/new.png']);
    });

    it('writes nothing to the clan when nothing changed', function () {
        ($this->sync)();
        $before = Clan::query()->sole()->updated_at;
        Date::setTestNow(now()->addHours(3));

        ($this->sync)();

        expect(Clan::query()->sole()->updated_at->toIso8601String())->toBe($before->toIso8601String());
    });
});

it('finds an existing clan by its normalised tag', function () {
    $clan = Clan::factory()->forTag('#2Q8URJ9L')->create(['name' => 'Fixture Clan', 'level' => 22]);

    $id = app(ClanDirectory::class)->ensure(new PlayerClanData(ClanTag::from('2q8urj9l'), 'Fixture Clan', 'member', 22, $clan->badge_urls));

    expect($id)->toBe($clan->id)->and(Clan::query()->count())->toBe(1);
});

it('keeps a known level when a payload has none', function () {
    $clan = Clan::factory()->forTag('#2Q8URJ9L')->create(['name' => 'Fixture Clan', 'level' => 22]);

    app(ClanDirectory::class)->ensure(new PlayerClanData(ClanTag::from('#2Q8URJ9L'), 'Fixture Clan', null, null, $clan->badge_urls));

    expect($clan->fresh()->level)->toBe(22);
});

it('leaves a clan the clan sync has fetched to that sync', function () {
    $clan = Clan::factory()->forTag('#2Q8URJ9L')->create(['name' => 'Synced Name', 'level' => 25]);
    $clan->forceFill(['api_synced_at' => now()->subHour()])->save();

    $id = app(ClanDirectory::class)->ensure(new PlayerClanData(ClanTag::from('#2Q8URJ9L'), 'Older Name', 'member', 24, []));

    expect($id)->toBe($clan->id)
        ->and($clan->fresh())->name->toBe('Synced Name')->level->toBe(25);
});

it('uses the row a concurrent request inserted after the lookup missed', function () {
    $inserted = false;
    // Another request inserts the tag right after this one's lookup found nothing.
    DB::listen(function (QueryExecuted $query) use (&$inserted): void {
        if (! $inserted && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"clans"')) {
            $inserted = true;
            DB::table('clans')->insert(['tag' => '#2Q8URJ9L', 'tag_normalized' => '2Q8URJ9L', 'name' => 'Fixture Clan', 'badge_urls' => '{}', 'level' => 22, 'api_sync_failures' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    $id = DB::transaction(fn (): int => app(ClanDirectory::class)->ensure(new PlayerClanData(ClanTag::from('#2Q8URJ9L'), 'Fixture Clan', 'member', 22, [])));

    expect($inserted)->toBeTrue()
        ->and(Clan::query()->count())->toBe(1)
        ->and($id)->toBe(Clan::query()->sole()->id);
});

it('relinks a released row reused on re-attach (specs/13 §6)', function () {
    $old = Clan::factory()->create();
    $released = CocAccount::factory()->forTag('#YC8V2QG9')->released()->create(['clan_id' => $old->id, 'clan_tag' => $old->tag]);

    $account = ($this->attach)('#YC8V2QG9');

    expect($account->id)->toBe($released->id)
        ->and($account)->clan_id->toBeNull()->clan_tag->toBeNull();
});

it('stores a clan without badges as an empty list (specs/23 §2: store what the API gives)', function () {
    $id = app(ClanDirectory::class)->ensure(new PlayerClanData(ClanTag::from('#2Q8URJ9L'), 'Private Clan', null, null, []));

    expect(Clan::query()->findOrFail($id))->badge_urls->toBe([])->level->toBeNull();
});

it('allows one clan row per tag', function () {
    Clan::factory()->forTag('#2Q8URJ9L')->create();

    expect(fn () => Clan::factory()->forTag('#2Q8URJ9L')->create())->toThrow(UniqueConstraintViolationException::class);
});

it('unlinks the accounts when a clan row is deleted (specs/08 §3)', function () {
    $clan = Clan::factory()->create();
    $account = CocAccount::factory()->create(['clan_id' => $clan->id]);

    $clan->delete();

    expect($account->fresh()->clan_id)->toBeNull();
});

it('reads clan summaries by id in one query, leaving out unknown ids', function () {
    $clans = Clan::factory()->count(2)->create();
    $withoutBadge = Clan::factory()->withoutBadge()->create(['level' => null]);

    $summaries = app(ClanReadModel::class)->summaries([$clans[0]->id, $withoutBadge->id, 999_999, $clans[0]->id]);

    expect(array_keys($summaries))->toEqualCanonicalizing([$clans[0]->id, $withoutBadge->id])
        ->and($summaries[$clans[0]->id])->tag->toBe($clans[0]->tag)->name->toBe('Factory Clan')->level->toBe(10)
        ->and($summaries[$clans[0]->id]->badgeUrls)->toEqual($clans[0]->badge_urls)
        ->and($summaries[$withoutBadge->id])->level->toBeNull()->badgeUrls->toBe([])
        ->and(app(ClanReadModel::class)->summaries([]))->toBe([]);
});

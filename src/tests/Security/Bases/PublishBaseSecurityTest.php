<?php

use App\Domain\Bases\Data\PublishBaseData;
use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseModerationState;
use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Enums\BaseVisibility;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Services\PublishBaseService;
use App\Domain\Bases\Support\BaseLink;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Media\Models\Media;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Validation\ValidationException;

// P3-01: the publish service is the trust boundary until the composer's route arrives (P3-08):
// the stored link, other users' uploads and accounts, mass assignment, and sanctions while a base
// waits on its media (specs/11, specs/12).

beforeEach(function () {
    $this->author = User::factory()->create();
    $this->author->forceFill(['verified_accounts_count' => 1])->save();
    CocAccount::factory()->for($this->author)->verified()->featured()->create();
});

function secureBase(array $overrides = []): PublishBaseData
{
    return new PublishBaseData(...[
        'title' => 'Ring',
        'description' => null,
        'thLevel' => 16,
        'category' => BaseCategory::War,
        'baseLink' => 'https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAAAAAASEC1',
        'visibility' => BaseVisibility::Public,
        ...$overrides,
    ]);
}

it('never stores a link that leaves the game host', function (string $typed) {
    expect(fn () => app(PublishBaseService::class)->handle($this->author, secureBase(['baseLink' => $typed])))->toThrow(ValidationException::class)
        ->and(BaseLayout::query()->count())->toBe(0);
})->with([
    'host in the userinfo' => ['https://link.clashofclans.com@evil.test/en?action=OpenLayout&id=TH16:WB:AAAAAAAA'],
    'look-alike host' => ['https://link.clashofclans.com.evil.test/en?action=OpenLayout&id=TH16:WB:AAAAAAAA'],
    'javascript scheme' => ['javascript:alert(1)//link.clashofclans.com/?action=OpenLayout&id=TH16:WB:AAAAAAAA'],
    'newline in the id' => ['https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAA%0aAAAA'],
    'null byte in the id' => ['https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAA%00AAAA'],
    'markup in the id' => ['https://link.clashofclans.com/en?action=OpenLayout&id=%22%3E%3Cscript%3EAAAA'],
    'id as an array' => ['https://link.clashofclans.com/en?action=OpenLayout&id[]=TH16:WB:AAAAAAAA'],
]);

it('rebuilds odd but valid game links into the one canonical form', function (string $typed) {
    expect(BaseLink::from($typed)->value)->toBe('https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3AWB%3AAAAAAAAASEC1');
})->with([
    'userinfo before the game host' => ['https://evil.test@link.clashofclans.com/en?action=OpenLayout&id=TH16:WB:AAAAAAAASEC1'],
    'a port' => ['https://link.clashofclans.com:8443/en?action=OpenLayout&id=TH16:WB:AAAAAAAASEC1'],
    'a backslash path' => ['https://link.clashofclans.com/\\evil.test?action=OpenLayout&id=TH16:WB:AAAAAAAASEC1'],
    'a fragment' => ['https://link.clashofclans.com/en?action=OpenLayout&id=TH16:WB:AAAAAAAASEC1#https://evil.test'],
]);

it("refuses another user's upload, and an upload already on another parent", function () {
    $foreign = Media::factory()->collection(MediaCollection::BaseScreenshot)->ready()->create();
    $mine = Media::factory()->collection(MediaCollection::BaseScreenshot)->ready()->create(['user_id' => $this->author->id]);
    app(PublishBaseService::class)->handle($this->author, secureBase(['screenshots' => [$mine->ulid]]));

    foreach ([$foreign->ulid, $mine->ulid] as $i => $ulid) {
        expect(fn () => app(PublishBaseService::class)->handle($this->author, secureBase([
            'screenshots' => [$ulid],
            'baseLink' => "https://link.clashofclans.com/en?action=OpenLayout&id=TH16:WB:AAAAAAAAOTH{$i}",
        ])))->toThrow(ValidationException::class);
    }
    expect($foreign->fresh()->attachable_id)->toBeNull()
        ->and(BaseLayout::query()->count())->toBe(1);
});

it("never credits another user's account", function () {
    $foreign = CocAccount::factory()->verified()->create();

    expect(fn () => app(PublishBaseService::class)->handle($this->author, secureBase(['accountUlid' => $foreign->ulid])))
        ->toThrow(ValidationException::class, 'Choose one of your verified accounts.');
});

it('keeps ownership, status and moderation fields out of mass assignment', function (string $field) {
    expect(fn () => new BaseLayout(['title' => 'T', $field => 'x']))->toThrow(MassAssignmentException::class)
        ->and(in_array($field, (new BaseLayout)->getFillable(), true))->toBeFalse();
})->with(['user_id', 'status', 'moderation_state', 'flagged_reason', 'base_link', 'layout_hash', 'coc_account_id', 'slug', 'published_at']);

it('keeps a waiting base off the site when its author is sanctioned before the media finish', function (string $sanction) {
    $pending = Media::factory()->collection(MediaCollection::BaseScreenshot)->ready()->create(['user_id' => $this->author->id, 'status' => MediaStatus::Processing]);
    app(PublishBaseService::class)->handle($this->author, secureBase(['screenshots' => [$pending->ulid]]));

    $sanctioned = User::factory()->{$sanction}()->make();
    $this->author->forceFill(['status' => $sanctioned->status, 'status_expires_at' => $sanctioned->status_expires_at])->save();
    $pending->forceFill(['status' => MediaStatus::Ready])->save();
    event(new MediaReady($pending->ulid, $this->author->id, MediaCollection::BaseScreenshot));

    expect(BaseLayout::query()->sole()->status)->toBe(BaseStatus::Processing);
})->with(['suspended', 'banned', 'restricted']);

it("hides a duplicate's flag from the publish result", function () {
    $other = User::factory()->create();
    $other->forceFill(['verified_accounts_count' => 1])->save();
    CocAccount::factory()->for($other)->verified()->create();
    app(PublishBaseService::class)->handle($other, secureBase());

    $result = app(PublishBaseService::class)->handle($this->author, secureBase());

    expect(BaseLayout::query()->where('user_id', $this->author->id)->sole()->moderation_state)->toBe(BaseModerationState::Flagged)
        ->and(array_keys(get_object_vars($result)))->toBe(['ulid', 'slug', 'status']);
});

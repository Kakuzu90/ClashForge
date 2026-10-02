<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\PlayerAccounts\Data\AccountDetailData;
use App\Domain\PlayerAccounts\Data\ProgressionGroupData;
use App\Domain\PlayerAccounts\Queries\AccountReadModel;
use App\Http\Controllers\Controller;
use App\Support\Seo\PageMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CoC account page (specs/18 §6), public and server-rendered. Who may see it is
 * `CocAccountPolicy::view`, inside the read model; an unknown and a hidden account get the same
 * 404 (specs/11 "Account enumeration"). It renders from stored data only, so it never waits on the
 * API (FR-COC-14). The grids are a deferred prop, behind their skeleton.
 */
class AccountController extends Controller
{
    public function show(Request $request, string $ulid, AccountReadModel $accounts): Response
    {
        $viewer = $request->user();
        $account = $accounts->detail($viewer, $ulid) ?? abort(Response::HTTP_NOT_FOUND);

        return PageMeta::page('Accounts/Show', [
            'account' => $account->toArray(),
            'progression' => Inertia::defer(fn (): array => array_map(
                fn (ProgressionGroupData $group): array => $group->toArray(),
                $accounts->progression($viewer, $ulid),
            )),
        ], $this->meta($account))->toResponse($request);
    }

    private function meta(AccountDetailData $account): PageMeta
    {
        $card = $account->card;
        $facts = array_filter([
            $card->townHallLevel === null ? null : "Town Hall {$card->townHallLevel}",
            $card->trophies === null ? null : number_format($card->trophies).' trophies',
            $card->clan?->name,
        ]);

        return new PageMeta(
            title: "{$card->name} ({$card->tag})",
            description: $facts === [] ? "{$card->name} on ".config('app.name').'.' : implode(' · ', $facts),
            canonical: route('accounts.show', ['ulid' => $card->ulid]),
            noindex: ! $account->indexable,
        );
    }
}

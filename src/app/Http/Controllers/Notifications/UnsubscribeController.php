<?php

namespace App\Http\Controllers\Notifications;

use App\Domain\Notifications\Enums\UnsubscribeOutcome;
use App\Domain\Notifications\Services\UnsubscribeService;
use App\Http\Controllers\Controller;
use App\Http\Data\Notifications\UnsubscribePageData;
use App\Support\Seo\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class UnsubscribeController extends Controller
{
    public function show(Request $request, string $ulid, string $hash, UnsubscribeService $unsubscribe): Response
    {
        $outcome = $unsubscribe->inspect($ulid, $hash, $request->fullUrl());

        return $this->page($outcome, $outcome === UnsubscribeOutcome::Pending ? $request->fullUrl() : null);
    }

    public function store(Request $request, string $ulid, string $hash, UnsubscribeService $unsubscribe): RedirectResponse
    {
        $outcome = $unsubscribe->unsubscribe($ulid, $hash, $request->fullUrl());

        return redirect()->route('notifications.unsubscribe.result')->with('unsubscribe_outcome', $outcome->value);
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $value = $request->session()->get('unsubscribe_outcome');
        $outcome = is_string($value) ? UnsubscribeOutcome::tryFrom($value) : null;

        return $outcome === null ? redirect()->route('home') : $this->page($outcome);
    }

    private function page(UnsubscribeOutcome $outcome, ?string $confirmUrl = null): Response
    {
        return PageMeta::page('Notifications/Unsubscribe', (new UnsubscribePageData($outcome->value, $outcome->label(), $confirmUrl))->toArray(), new PageMeta(title: 'Email preferences', noindex: true));
    }
}

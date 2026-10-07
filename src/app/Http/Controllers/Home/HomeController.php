<?php

namespace App\Http\Controllers\Home;

use App\Domain\Bases\Queries\BaseFeedQuery;
use App\Domain\Bases\Services\FeedDefaults;
use App\Http\Controllers\Bases\FeedProps;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bases\HomeFeedRequest;
use App\Support\Seo\PageMeta;
use Inertia\Response;

/**
 * The home feed (specs/18 §6, specs/17 §6): trending bases with a Town Hall chip row and three
 * sorts. Signed in, it starts at the featured account's Town Hall ±1.
 */
class HomeController extends Controller
{
    public function __invoke(HomeFeedRequest $request, BaseFeedQuery $feed, FeedDefaults $defaults): Response
    {
        return PageMeta::page('Home/Index', FeedProps::for($request, $feed, $defaults, defaultTh: true));
    }
}

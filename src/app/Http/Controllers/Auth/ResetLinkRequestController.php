<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Services\PasswordResetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestPasswordResetLinkRequest;
use App\Http\Responses\Auth\PasswordResetLinkResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Replaces Fortify's link request: Fortify creates the token (a bcrypt hash) inside the request,
 * which makes known emails measurably slower than unknown ones.
 */
class ResetLinkRequestController extends Controller
{
    public function store(RequestPasswordResetLinkRequest $request, PasswordResetService $resets): RedirectResponse
    {
        $resets->requestLink($request->string('email')->toString());

        return (new PasswordResetLinkResponse)->toResponse($request);
    }
}

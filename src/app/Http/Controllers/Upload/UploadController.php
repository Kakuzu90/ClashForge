<?php

namespace App\Http\Controllers\Upload;

use App\Domain\Media\Actions\CompleteUpload;
use App\Domain\Media\Services\UploadIntentService;
use App\Domain\Media\Services\UploadStatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\CreateUploadIntentRequest;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints behind the upload composable (specs/10 §3). Authorization happens in the Media
 * services through MediaPolicy, because controllers may not reference domain models.
 */
class UploadController extends Controller
{
    public function intent(CreateUploadIntentRequest $request, UploadIntentService $intents): JsonResponse
    {
        return response()->json($intents->create($this->user($request), $request->toData()), 201);
    }

    public function complete(Request $request, string $media, CompleteUpload $complete): JsonResponse
    {
        return response()->json($complete->handle($this->user($request), $media), 202);
    }

    public function show(Request $request, string $media, UploadStatusService $status): JsonResponse
    {
        return response()->json($status->forOwner($this->user($request), $media));
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return $user;
    }
}

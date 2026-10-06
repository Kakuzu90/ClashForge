<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Operations\Data\FailedJobActionResultData;
use App\Domain\Operations\Services\FailedJobService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FailedJobActionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Retry or delete failed jobs from the System Health page (specs/20 §5, P2-19). The service
 * authorizes `manage-failed-jobs` (admin+, active account) and audits each job.
 */
class FailedJobController extends Controller
{
    public function retry(FailedJobActionRequest $request, FailedJobService $failedJobs): RedirectResponse
    {
        $result = $failedJobs->retry($this->admin($request), $request->target());

        return back()->with('success', self::summary($result, 'Retried'));
    }

    public function destroy(FailedJobActionRequest $request, FailedJobService $failedJobs): RedirectResponse
    {
        $result = $failedJobs->delete($this->admin($request), $request->target());

        return back()->with('success', self::summary($result, 'Deleted'));
    }

    private static function summary(FailedJobActionResultData $result, string $verb): string
    {
        $jobs = fn (int $n): string => $n === 1 ? '1 job' : "{$n} jobs";
        $parts = ["{$verb} ".$jobs($result->done).'.'];
        if ($result->skipped > 0) {
            $parts[] = ucfirst($jobs($result->skipped)).' had already been handled.';
        }
        if ($result->kept > 0) {
            $parts[] = ucfirst($jobs($result->kept)).' could not be retried and can only be deleted.';
        }
        if ($result->left > $result->kept) {
            $parts[] = ucfirst($jobs($result->left - $result->kept)).' left; run it again for the rest.';
        }

        return implode(' ', $parts);
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

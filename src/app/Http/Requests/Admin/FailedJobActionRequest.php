<?php

namespace App\Http\Requests\Admin;

use App\Domain\Operations\Data\FailedJobTarget;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Which failed jobs to retry or delete (P2-19): exactly one of a job's `uuid`, a job `class` from
 * the System Health list, or `unreadable` for the jobs whose payload has no class. FailedJobService
 * checks the target still has jobs.
 */
class FailedJobActionRequest extends FormRequest
{
    /**
     * Authorization runs in FailedJobService through the `manage-failed-jobs` Gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'string', 'uuid'],
            'class' => ['nullable', 'string', 'max:255'],
            'unreadable' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $given = array_filter([$this->filled('uuid'), $this->filled('class'), $this->boolean('unreadable')]);
                if (count($given) !== 1) {
                    $validator->errors()->add('target', 'Choose one job or one kind of job.');
                }
            },
        ];
    }

    public function target(): FailedJobTarget
    {
        return match (true) {
            $this->filled('uuid') => FailedJobTarget::job($this->string('uuid')->toString()),
            $this->filled('class') => FailedJobTarget::ofClass($this->string('class')->toString()),
            default => FailedJobTarget::unreadable(),
        };
    }
}

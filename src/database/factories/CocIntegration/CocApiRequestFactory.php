<?php

namespace Database\Factories\CocIntegration;

use App\Domain\CocIntegration\Models\CocApiRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CocApiRequest>
 */
class CocApiRequestFactory extends Factory
{
    protected $model = CocApiRequest::class;

    /**
     * A successful player fetch.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'endpoint' => 'players',
            'tag' => '#2PQ8GRJC',
            'status_code' => 200,
            'duration_ms' => 180,
            'was_cached' => false,
            'error_code' => null,
        ];
    }

    public function cached(): static
    {
        return $this->state(['was_cached' => true, 'duration_ms' => 0]);
    }

    public function timedOut(): static
    {
        return $this->state(['status_code' => null, 'duration_ms' => 10000, 'error_code' => 'timeout']);
    }

    public function failed(int $status = 503, string $reason = 'inMaintenance'): static
    {
        return $this->state(['status_code' => $status, 'error_code' => $reason]);
    }
}

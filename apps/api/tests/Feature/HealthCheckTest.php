<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_the_versioned_api_health_endpoint_is_available(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'grassroots-basketball-api')
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'service',
                    'environment',
                    'timestamp',
                ],
            ]);
    }
}

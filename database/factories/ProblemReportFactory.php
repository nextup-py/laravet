<?php

namespace Database\Factories;

use App\Enums\ProblemReportStatus;
use App\Enums\ProblemReportType;
use App\Models\ProblemReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProblemReport>
 */
class ProblemReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'user_name' => fake()->name(),
            'type' => ProblemReportType::Error,
            'description' => fake()->sentence(12),
            'screen' => 'https://laravet.test/mascotas',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0',
            'app_version' => 'abc1234',
            'attachment' => null,
            'status' => ProblemReportStatus::New,
        ];
    }
}

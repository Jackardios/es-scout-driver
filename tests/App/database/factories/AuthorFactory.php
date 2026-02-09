<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\App\database\factories;

use Jackardios\EsScoutDriver\Tests\App\Author;
use Illuminate\Database\Eloquent\Factories\Factory;

final class AuthorFactory extends Factory
{
    protected $model = Author::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone_number' => $this->faker->phoneNumber(),
        ];
    }
}

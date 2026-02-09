<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\App\database\factories;

use Jackardios\EsScoutDriver\Tests\App\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

final class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'lat' => $this->faker->latitude(),
            'lon' => $this->faker->longitude(),
        ];
    }
}

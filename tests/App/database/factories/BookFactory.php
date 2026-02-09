<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\App\database\factories;

use Jackardios\EsScoutDriver\Tests\App\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

final class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'price' => $this->faker->randomFloat(2, 1, 100),
            'description' => $this->faker->paragraph(),
            'tags' => $this->faker->randomElements(['fiction', 'science', 'history', 'romance', 'thriller'], 2),
        ];
    }
}

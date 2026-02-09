<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\App;

use Jackardios\EsScoutDriver\Searchable;
use Jackardios\EsScoutDriver\Tests\App\database\factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected static function newFactory(): BookFactory
    {
        return BookFactory::new();
    }

    protected $fillable = ['title', 'author', 'price', 'description', 'tags'];

    protected $casts = [
        'tags' => 'array',
        'price' => 'float',
    ];

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'author' => $this->author,
            'price' => $this->price,
            'description' => $this->description,
            'tags' => $this->tags,
        ];
    }
}

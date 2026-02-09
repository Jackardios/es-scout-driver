<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\App;

use Jackardios\EsScoutDriver\Searchable;
use Jackardios\EsScoutDriver\Tests\App\database\factories\AuthorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use HasFactory;
    use Searchable;

    protected static function newFactory(): AuthorFactory
    {
        return AuthorFactory::new();
    }

    protected $fillable = ['name', 'email', 'phone_number'];

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
        ];
    }
}

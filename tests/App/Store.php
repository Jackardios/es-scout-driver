<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\App;

use Jackardios\EsScoutDriver\Searchable;
use Jackardios\EsScoutDriver\Tests\App\database\factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;
    use Searchable;

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }

    protected $fillable = ['name', 'lat', 'lon'];

    protected $casts = [
        'lat' => 'float',
        'lon' => 'float',
    ];

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'location' => [
                'lat' => $this->lat,
                'lon' => $this->lon,
            ],
        ];
    }
}

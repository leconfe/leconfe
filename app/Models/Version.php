<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    use Cachable;

    protected $fillable = [
        'product_name',
        'product_folder',
        'version',
    ];

    protected $casts = [
        'installed_at' => 'timestamp',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Version $version) {
            $version->installed_at = now();
        });
    }

    public static function application()
    {
        $version = static::query()
            ->where('product_name', 'Leconfe')
            ->where('product_folder', 'leconfe')
            ->orderBy('installed_at', 'desc')
            ->first();

        if (! $version) {
            $version = app()->getVersion();
            $version->save();
        }

        return $version;
    }

    protected function major(): Attribute
    {
        return Attribute::make(
            get: fn () => explode('.', $this->version)[0],
        );
    }

    protected function minor(): Attribute
    {
        return Attribute::make(
            get: fn () => explode('.', $this->version)[1],
        );
    }

    protected function patch(): Attribute
    {
        return Attribute::make(
            get: function () {
                $version = explode('.', $this->version)[2];

                if (array_key_exists(3, explode('.', $this->version))) {
                    $version .= '.'.explode('.', $this->version)[3];
                }

                return $version;
            },
        );
    }
}

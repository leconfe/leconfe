<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Metric extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = [];

    protected $cast = [
        'date' => 'date',
    ];
    

    public static function purgeBySource(string $source)
    {
        return static::where('source', $source)->delete();
    }
}

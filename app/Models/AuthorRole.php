<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\HasBuiltinLabel;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class AuthorRole extends Model implements Sortable
{
    use BelongsToConference, Cachable, HasBuiltinLabel, HasFactory, SortableTrait;

    protected $table = 'author_roles';

    public static function builtinNames(): array
    {
        return [
            'author' => 'Author',
            'translator' => 'Translator',
        ];
    }

    protected $fillable = [
        'conference_id',
        'parent_id',
        'name',
    ];

    /**
     * @var array<int, string>
     */
    protected array $translatableColumns = ['name'];

    public function authors(): HasMany
    {
        return $this->hasMany(Author::class);
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}

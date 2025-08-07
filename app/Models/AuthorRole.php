<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\LocalizedMetable;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class AuthorRole extends Model implements Sortable
{
    use BelongsToConference, Cachable, HasFactory, LocalizedMetable, SortableTrait;

    protected $table = 'author_roles';

    protected $fillable = [
        'conference_id',
        'parent_id',
        'name',
    ];

    public function authors(): HasMany
    {
        return $this->hasMany(Author::class);
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function getNameAttribute(): ?string
    {
        return $this->getLocalizedMeta('name') ?? $this->attributes['name'] ?? null;
    }
}

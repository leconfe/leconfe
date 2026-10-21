<?php

namespace App\Models;

use App\Frontend\Conference\Pages\ProceedingDetail;
use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\HasDOI;
use App\Models\Concerns\HasTranslatableContent;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Proceeding extends Model implements HasMedia, Sortable
{
    use BelongsToConference, Cachable, HasDOI, HasFactory, HasTranslatableContent, InteractsWithMedia, Metable, SortableTrait;

    protected $table = 'proceedings';

    protected $fillable = [
        'title',
        'description',
        'volume',
        'number',
        'year',
        'subject',
        'isbn',
        'published',
        'published_at',
        'current',
    ];

    protected $casts = [
        'published' => 'boolean',
        'published_at' => 'datetime',
        'current' => 'boolean',
    ];

    /**
     * @var array<int, string>
     */
    protected array $translatableColumns = ['title', 'description'];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Proceeding $proceeding) {
            if ($proceeding->submissions->isNotEmpty()) {
                throw new \Exception('Could not delete proceeding with submissions, please remove submissions first.');
            }
        });
    }

    public function scopePublished($query, $published = true)
    {
        return $query->where('published', $published);
    }

    public function scopeCurrent($query, $current = true)
    {
        return $query->where('current', $current);
    }

    public function isPublished(): bool
    {
        return $this->published && $this->published_at !== null;
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('avatar')
            ->keepOriginalImageFormat()
            ->width(50);

        $this->addMediaConversion('thumb')
            ->keepOriginalImageFormat()
            ->width(400);

        $this->addMediaConversion('thumb-xl')
            ->keepOriginalImageFormat()
            ->width(800);
    }

    public function publish($published = true): self
    {
        $this->published = $published;
        $this->published_at ??= now();
        $this->save();

        $this->setAsCurrent();

        return $this;
    }

    public function unpublish(): self
    {
        return $this->publish(false);
    }

    public function setAsCurrent(): self
    {
        // Current only one for each conference
        $this->newQuery()->where('conference_id', $this->conference_id)->update(['current' => false]);

        $this->current = true;
        $this->save();

        return $this;
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function seriesTitle(): string
    {
        $string = '';

        if ($this->volume) {
            $string .= "Vol. {$this->volume}";
        }

        if ($this->number) {
            $string .= " No. {$this->number}";
        }

        if ($this->year) {
            $string .= " ({$this->year})";
        }

        if (! empty($string)) {
            $string .= ': ';
        }

        return $string.$this->translated('title');
    }

    public function getUrl(): string
    {
        return route(ProceedingDetail::getRouteName(), [
            'proceeding' => $this,
            'conference' => $this->conference,
        ]);
    }

    /**
     * Resolve the model from a route parameter.
     *
     * PostgreSQL rejects a non-numeric value when it is compared with this
     * model's bigint primary key. Treat an invalid ID as a missing model so
     * Laravel returns its normal 404 response instead.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if (($field ?? $this->getRouteKeyName()) === $this->getKeyName() && ! ctype_digit((string) $value)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'additional_content' => [],
        ];
    }
}

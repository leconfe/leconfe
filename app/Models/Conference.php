<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Vite;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Conference extends Model implements HasAvatar, HasMedia, HasName
{
    use Cachable, HasFactory, HasSlug, InteractsWithMedia, Metable;

    public const SCOPE_INTERNATIONAL = 'international';

    public const SCOPE_NATIONAL = 'national';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'status',
        'path',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Conference $conference) {
            ScheduledConference::query()
                ->withoutGlobalScopes()
                ->where('conference_id', $conference->getKey())
                ->get()
                ->each
                ->forceDelete();

            DOI::query()
                ->withoutGlobalScopes()
                ->where('conference_id', $conference->getKey())
                ->get()
                ->each
                ->delete();

            Role::query()
                ->withoutGlobalScopes()
                ->where('conference_id', $conference->getKey())
                ->lazy()
                ->each
                ->delete();

            PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('conference_id', $conference->getKey())
                ->lazy()
                ->each
                ->delete();

            NavigationMenu::query()
                ->with(['items'])
                ->withoutGlobalScopes()
                ->where('conference_id', $conference->getKey())
                ->lazy()
                ->each
                ->delete();
        });
    }

    public function submission(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function staticPages(): HasMany
    {
        return $this->hasMany(StaticPage::class);
    }

    public function navigations(): HasMany
    {
        return $this->hasMany(NavigationMenu::class);
    }

    public function authorRoles(): HasMany
    {
        return $this->hasMany(AuthorRole::class);
    }

    public function getNavigationItems(string $handle): array
    {
        return $this->navigations->firstWhere('handle', $handle)?->items ?? [];
    }

    public function scheduledConferences(): HasMany
    {
        return $this->hasMany(ScheduledConference::class);
    }

    public function currentScheduledConference(): HasOne
    {
        return $this->hasOne(ScheduledConference::class)->published()->orderBy('date_start', 'DESC');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function conferenceUsers()
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles');
    }

    public function proceedings(): HasMany
    {
        return $this->hasMany(Proceeding::class);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo', 'tenant');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('tenant')
            ->keepOriginalImageFormat()
            ->width(50);

        $this->addMediaConversion('thumb')
            ->keepOriginalImageFormat()
            ->width(400);

        $this->addMediaConversion('thumb-xl')
            ->keepOriginalImageFormat()
            ->width(800);
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('path')
            ->skipGenerateWhen(fn () => $this->path !== null);
    }

    public function getPanelUrl(): string
    {
        return route('filament.conference.pages.dashboard', ['conference' => $this->path]);
    }

    public function getHomeUrl(): string
    {
        return route('livewirePageGroup.conference.pages.home', ['conference' => $this->path]);
    }

    public function hasThumbnail(): bool
    {
        return $this->getMedia('thumbnail')->isNotEmpty();
    }

    public function getThumbnailUrl(): string
    {
        return $this->getFirstMedia('thumbnail')?->getAvailableUrl(['thumb', 'thumb-xl']) ?? Vite::asset('resources/assets/images/placeholder-vertical.jpg');
    }

    public function getLicenseUrl(): string
    {
        $licenseUrl = $this->getMeta('license_url');
        if ($licenseUrl == 'custom') {
            return $this->getMeta('license_url_custom');
        }

        return $licenseUrl;
    }

    public function getCopyrightHolderForSubmission(Submission $submission)
    {
        return match ($this->getMeta('copyright_holder')) {
            'author' => $submission->authors->reduce(function ($carry, $author) {
                $carry .= $author->fullName.'; ';

                return $carry;
            }),
            'conference' => $this->name,
            'custom' => $this->getMeta('custom_copyright_holder'),
        };
    }

    public function getCopyrightYearForSubmission(Submission $submission)
    {
        return match ($this->getMeta('copyright_year')) {
            'proceeding' => $submission->proceeding?->published_at?->format('Y') ?? now()->format('Y'),
            'paper' => $submission->published_at?->format('Y') ?? now()->format('Y'),
        };
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'settings_select_format_date' => 'j F Y',
            'settings_format_date' => 'j F Y',
            'settings_select_format_time' => 'H:i',
            'settings_format_time' => 'H:i',
            'settings_default_language' => 'en',
            'settings_languages' => ['en'],
            'page_footer' => view('frontend.examples.footer')->render(),
            'languages' => ['en'],
            'primary_citation_format' => 'apa',
            'enabled_citation_styles' => [
                'harvard-cite-them-right',
                'ieee',
                'modern-language-association',
                'turabian-fullnote-bibliography',
                'vancouver',
                'ama',
                'chicago-author-date',
                'associacao-brasileira-de-normas-tecnicas',
                'apa',
                'acs-nano',
                'acm-sig-proceedings',
            ],
            'downloadable_citation_formats' => [
                'ris',
                'bibtex',
            ],
            'copyright_holder' => 'conference',
            'license_url' => 'https://creativecommons.org/licenses/by-nc-nd/4.0',
            'copyright_year' => 'paper',
            'theme' => 'DefaultTheme',
        ];
    }

    public function getContextString() : string
    {
        return 'conference';
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatableContent;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class NavigationMenuItem extends Model implements Sortable
{
    use Cachable, HasFactory, HasTranslatableContent, Metable, SortableTrait;

    protected $fillable = [
        'label',
        'type',
        'navigation_menu_id',
        'parent_id',
    ];

    /**
     * Columns whose content can be translated.
     *
     * @var array<int, string>
     */
    protected array $translatableColumns = ['label'];

    /**
     * Interface text for item types whose default label is built in.
     *
     * @var array<string, string>
     */
    protected const BUILT_IN_LABELS = [
        'home' => 'general.home',
        'about' => 'general.about',
        'proceedings' => 'general.proceedings',
        'announcements' => 'general.announcements',
        'login' => 'general.login',
        'register' => 'general.register',
        'dashboard' => 'general.dashboard',
        'profile' => 'general.profile',
        'logout' => 'general.logout',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Model $model) {
            $model->children()->delete();
        });
    }

    public function navigationMenu(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function buildSortQuery()
    {
        return static::query()
            ->where('navigation_menu_id', $this->navigation_menu_id)
            ->where('parent_id', $this->parent_id);
    }

    public static function getTypes(): array
    {
        return [
            'remote-url' => NavigationItemType\RemoteUrl::class,
            'about' => NavigationItemType\About::class,
            'contact' => NavigationItemType\Contact::class,
            'announcements' => NavigationItemType\Announcements::class,
            'dashboard' => NavigationItemType\Dashboard::class,
            'home' => NavigationItemType\Home::class,
            'login' => NavigationItemType\Login::class,
            'logout' => NavigationItemType\Logout::class,
            'proceedings' => NavigationItemType\Proceedings::class,
            'profile' => NavigationItemType\Profile::class,
            'register' => NavigationItemType\Register::class,
            'search' => NavigationItemType\Search::class,
            'submissions' => NavigationItemType\Submissions::class,
        ];
    }

    public static function getType(string $type): string
    {
        return self::getTypes()[$type];
    }

    public static function getTypeOptions(): array
    {
        return collect(self::getTypes())
            ->mapWithKeys(fn ($type) => [$type::getId() => $type::getLabel()])
            ->toArray();
    }

    public function getUrl(): string
    {
        return self::getType($this->type)::getUrl($this) ?? '#';
    }

    public function getLabel(): string
    {
        $label = (string) $this->translated('label');

        if ($this->hasBuiltInLabel($label)) {
            $label = __(static::BUILT_IN_LABELS[$this->type]);
        }

        // replace {$username} with the user's name
        if (auth()->check() && str_contains($label, '{$username}')) {
            $label = str_replace('{$username}', auth()->user()->fullName, $label);
        }

        return $label;
    }

    /**
     * Whether the label is still the built-in English label of its type and has no
     * translation for the active language, so the interface text is shown.
     */
    protected function hasBuiltInLabel(string $label): bool
    {
        return array_key_exists($this->type, static::BUILT_IN_LABELS)
            && $label === static::getType($this->type)::getLabel()
            && $this->translatedLocale('label') !== app()->getLocale();
    }

    public function isDisplayed(): bool
    {
        return self::getType($this->type)::getIsDisplayed($this);
    }
}

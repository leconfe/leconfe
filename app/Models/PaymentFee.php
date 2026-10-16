<?php

namespace App\Models;

use App\Managers\PaymentManager;
use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\HasTranslatableContent;
use App\Support\Multilingual\ContentLocales;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class PaymentFee extends Model implements Sortable
{
    use BelongsToConference, BelongsToScheduledConference, HasFactory, HasTranslatableContent, Metable, SortableTrait;

    /**
     * Translated fields of each add-on item.
     */
    public const ADDITIONAL_ITEM_TRANSLATABLE_FIELDS = ['name', 'description'];

    protected $fillable = [
        'name',
        'type',
        'amount',
        'currency',
        'is_active',
        'is_public',
        'limit',
        'order_column',
        'opened_at',
        'closed_at',
        'scheduled_conference_id',
        'conference_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'opened_at' => 'date',
        'closed_at' => 'date',
    ];

    /**
     * @var array<int, string>
     */
    protected array $translatableColumns = ['name'];

    /**
     * @var array<int, string>
     */
    protected array $translatableMeta = ['description'];

    /**
     * Add-on item translations collected from form state, in the order of the items,
     * written by persistAdditionalItemTranslations().
     *
     * @var array<int, array{translations: array<string, array<string, mixed>>, copyFrom: ?string}>|null
     */
    protected ?array $pendingAdditionalItemTranslations = null;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (PaymentFee $paymentFee) {
            $paymentFee->load('payments');

            $paymentFee->payments->each->delete();
        });
    }

    public function scopeType($query, $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive($query, $active = true): Builder
    {
        $query->where('is_active', $active);

        if (! $active) {
            return $query;
        }

        $now = now();

        return $query
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('opened_at')
                    ->orWhereDate('opened_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('closed_at')
                    ->orWhereDate('closed_at', '>=', $now);
            });
    }

    public function formItems(): HasMany
    {
        return $this->hasMany(PaymentFeeFormItem::class);
    }

    public function getPaymentType()
    {
        return PaymentManager::get()->getPaymentTypeName($this->type);
    }

    public function getFormattedFee()
    {
        return money($this->amount, $this->currency, true)->formatWithoutZeroes();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'additional_items' => [],
        ];
    }

    /**
     * The key of an add-on item, derived from its name, amount and description, or
     * null when the item is not valid. Payments refer to selected items by this key.
     */
    public static function additionalItemKey(mixed $item): ?string
    {
        if (! is_array($item)) {
            return null;
        }

        $name = trim((string) data_get($item, 'name', ''));
        $amount = (float) data_get($item, 'amount', 0);

        if ($name === '' || $amount < 0) {
            return null;
        }

        $description = data_get($item, 'description');

        return 'addon_'.md5($name.'|'.$amount.'|'.(string) $description);
    }

    public function getAdditionalItems(): array
    {
        $additionalItems = $this->getMeta('additional_items', []);

        if (! is_array($additionalItems)) {
            return [];
        }

        return collect($additionalItems)
            ->values()
            ->map(function ($item) {
                $key = static::additionalItemKey($item);

                if ($key === null) {
                    return null;
                }

                return [
                    'key' => $key,
                    'name' => trim((string) data_get($item, 'name', '')),
                    'description' => data_get($item, 'description'),
                    'amount' => (float) data_get($item, 'amount', 0),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Add-on items with their name and description in the given language, or the
     * current language.
     */
    public function getTranslatedAdditionalItems(?string $locale = null): array
    {
        return collect($this->getAdditionalItems())
            ->map(fn (array $item) => $this->translatedAdditionalItem($item, $locale))
            ->all();
    }

    /**
     * An add-on item with its name and description in the given language, falling back
     * to the primary language and then to the stored item.
     */
    public function translatedAdditionalItem(array $item, ?string $locale = null): array
    {
        $key = $item['key'] ?? static::additionalItemKey($item);

        if (! is_string($key)) {
            return $item;
        }

        foreach (static::ADDITIONAL_ITEM_TRANSLATABLE_FIELDS as $field) {
            $value = $this->translatedAdditionalItemField($key, $field, $locale);

            if ($value !== null) {
                $item[$field] = $value;
            }
        }

        return $item;
    }

    /**
     * Localize the add-on items stored on a payment. Items whose key no longer matches
     * an add-on of this fee keep the stored text.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function localizeAdditionalItems(array $items, ?string $locale = null): array
    {
        return collect($items)
            ->map(function (mixed $item) use ($locale) {
                if (! is_array($item) || ! is_string($item['key'] ?? null)) {
                    return $item;
                }

                foreach (static::ADDITIONAL_ITEM_TRANSLATABLE_FIELDS as $field) {
                    $value = $this->translatedAdditionalItemField($item['key'], $field, $locale);

                    if ($value !== null) {
                        $item[$field] = $value;
                    }
                }

                return $item;
            })
            ->all();
    }

    public static function additionalItemTranslationKey(string $itemKey, string $field): string
    {
        return "additional_items.{$itemKey}.{$field}";
    }

    protected function translatedAdditionalItemField(string $itemKey, string $field, ?string $locale = null): ?string
    {
        $locales = array_unique([$locale ?? app()->getLocale(), $this->getPrimaryContentLocale()]);

        foreach ($locales as $candidate) {
            $value = $this->getStoredTranslation(static::additionalItemTranslationKey($itemKey, $field), $candidate);

            if (! static::isBlankContent($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Prepare the add-on items of form state for editing: each item remembers its key,
     * and with more than one language its name and description hold a value per locale.
     */
    public function fillAdditionalItemFormState(array $data): array
    {
        $items = data_get($data, 'meta.additional_items');

        if (! is_array($items)) {
            return $data;
        }

        $locales = $this->getContentLocalesForForm();
        $primary = $this->getPrimaryContentLocale();

        $items = collect($items)
            ->map(function (mixed $item) use ($locales, $primary) {
                if (! is_array($item)) {
                    return $item;
                }

                $key = static::additionalItemKey($item);
                $item['original_key'] = $key;

                if (count($locales) < 2) {
                    return $item;
                }

                foreach (static::ADDITIONAL_ITEM_TRANSLATABLE_FIELDS as $field) {
                    $item[$field] = collect($locales)
                        ->mapWithKeys(function (string $locale) use ($item, $key, $field, $primary) {
                            $value = $key ? $this->getStoredTranslation(static::additionalItemTranslationKey($key, $field), $locale) : null;

                            if ($locale === $primary && static::isBlankContent($value)) {
                                $value = $item[$field] ?? null;
                            }

                            return [$locale => $value];
                        })
                        ->all();
                }

                return $item;
            })
            ->all();

        Arr::set($data, 'meta.additional_items', $items);

        return $data;
    }

    /**
     * Put the primary-language name and description of each add-on item back in place
     * and remember the other languages for persistAdditionalItemTranslations().
     */
    public function syncAdditionalItemState(array $data): array
    {
        $items = data_get($data, 'meta.additional_items');

        if (! is_array($items)) {
            return $data;
        }

        $primary = $this->getPrimaryContentLocale();
        $enabled = array_values(array_unique([$primary, ...app(ContentLocales::class)->enabled()]));
        $pending = [];
        $synced = [];

        foreach (array_values($items) as $item) {
            if (! is_array($item)) {
                $synced[] = $item;
                $pending[] = ['translations' => [], 'copyFrom' => null];

                continue;
            }

            $copyFrom = $item['original_key'] ?? null;
            unset($item['original_key']);

            $translations = [];

            foreach (static::ADDITIONAL_ITEM_TRANSLATABLE_FIELDS as $field) {
                $value = $item[$field] ?? null;

                if (is_array($value)) {
                    $translations[$field] = Arr::only($value, $enabled);
                    $item[$field] = $value[$primary] ?? null;
                }
            }

            $synced[] = $item;
            $pending[] = ['translations' => $translations, 'copyFrom' => is_string($copyFrom) ? $copyFrom : null];
        }

        Arr::set($data, 'meta.additional_items', $synced);
        $this->pendingAdditionalItemTranslations = $pending;

        return $data;
    }

    /**
     * Write the add-on item translations under each item's current key. An item saved
     * with single-language state keeps the translations of the key it had before it was
     * edited. Translations of items that no longer exist are removed.
     */
    public function persistAdditionalItemTranslations(): void
    {
        if ($this->pendingAdditionalItemTranslations === null || ! ContentLocales::hasTranslationStorage()) {
            return;
        }

        $items = $this->getMeta('additional_items', []);
        $items = is_array($items) ? array_values($items) : [];
        $primary = $this->getPrimaryContentLocale();
        $keep = [];

        foreach ($items as $index => $item) {
            $key = static::additionalItemKey($item);

            if ($key === null) {
                continue;
            }

            $keep[] = $key;
            $pending = $this->pendingAdditionalItemTranslations[$index] ?? ['translations' => [], 'copyFrom' => null];

            foreach (static::ADDITIONAL_ITEM_TRANSLATABLE_FIELDS as $field) {
                $translationKey = static::additionalItemTranslationKey($key, $field);

                if (array_key_exists($field, $pending['translations'])) {
                    foreach ($pending['translations'][$field] as $locale => $value) {
                        $this->writeTranslation($translationKey, $locale, is_scalar($value) ? $value : null);
                    }

                    continue;
                }

                if ($pending['copyFrom'] === null || $pending['copyFrom'] === $key) {
                    continue;
                }

                $this->translate()
                    ->where('key', static::additionalItemTranslationKey($pending['copyFrom'], $field))
                    ->get()
                    ->each(fn ($translation) => $this->writeTranslation(
                        $translationKey,
                        $translation->locale,
                        $translation->locale === $primary ? ($item[$field] ?? null) : $translation->value,
                    ));
            }
        }

        $this->translate()
            ->where('key', 'like', 'additional_items.%')
            ->get()
            ->filter(fn ($translation) => str_starts_with($translation->key, 'additional_items.'))
            ->reject(fn ($translation) => in_array(explode('.', $translation->key)[1] ?? null, $keep, true))
            ->each
            ->delete();

        $this->pendingAdditionalItemTranslations = null;
        $this->refreshTranslationCache();
    }

    public function getAdditionalItemOptions(): array
    {
        return collect($this->getTranslatedAdditionalItems())
            ->mapWithKeys(function (array $item) {
                $formattedAmount = money($item['amount'], $this->currency, true)->formatWithoutZeroes();

                return [
                    $item['key'] => "{$item['name']} ({$formattedAmount})",
                ];
            })
            ->all();
    }

    public function resolveSelectedAdditionalItems(?array $selectedData = null): array
    {
        if (! is_array($selectedData) || $selectedData === []) {
            return [];
        }

        $items = [];

        foreach ($selectedData as $item) {
            if (is_array($item)) {
                $key = data_get($item, 'key');
                $quantity = (int) data_get($item, 'quantity', 1);

                if ($key && $quantity > 0) {
                    $additionalItem = collect($this->getAdditionalItems())
                        ->firstWhere('key', $key);

                    if ($additionalItem) {
                        $items[] = [
                            'key' => $key,
                            'name' => $additionalItem['name'],
                            'description' => $additionalItem['description'] ?? null,
                            'amount' => (float) $additionalItem['amount'],
                            'quantity' => $quantity,
                            'total_amount' => (float) $additionalItem['amount'] * $quantity,
                        ];
                    }
                }
            } elseif (is_string($item)) {
                $additionalItem = collect($this->getAdditionalItems())
                    ->firstWhere('key', $item);

                if ($additionalItem) {
                    $items[] = [
                        'key' => $item,
                        'name' => $additionalItem['name'],
                        'description' => $additionalItem['description'] ?? null,
                        'amount' => (float) $additionalItem['amount'],
                        'quantity' => 1,
                        'total_amount' => (float) $additionalItem['amount'],
                    ];
                }
            }
        }

        return $items;
    }

    public function resolveSelectedAdditionalItemsByKeys(?array $selectedKeys = null): array
    {
        if (! is_array($selectedKeys) || $selectedKeys === []) {
            return [];
        }

        $selectedKeys = array_values(array_unique(array_map('strval', $selectedKeys)));

        return collect($this->getAdditionalItems())
            ->whereIn('key', $selectedKeys)
            ->values()
            ->all();
    }

    public function getAdditionalItemFormSchema(): array
    {
        $items = $this->getTranslatedAdditionalItems();
        $currency = $this->currency;

        if (empty($items)) {
            return [];
        }

        $schema = collect($items)->map(function (array $item) use ($currency) {
            $key = $item['key'];
            $name = $item['name'];
            $amount = $item['amount'];
            $description = $item['description'] ?? '';
            $formattedAmount = money($amount, $currency, true)->formatWithoutZeroes();

            return TextInput::make("additional_items.{$key}")
                ->label($name)
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->helperText($description ? "{$description} - {$formattedAmount} each" : "{$formattedAmount} each")
                ->suffixAction(
                    \Filament\Actions\StaticAction::make('clear')
                        ->icon('heroicon-x-mark')
                        ->action(function (Set $set) use ($key) {
                            $set("additional_items.{$key}", 0);
                        })
                );
        })->toArray();

        return [
            \Filament\Forms\Components\Fieldset::make('Add-on Items')
                ->schema($schema)
                ->visible(fn () => count($items) > 0),
        ];
    }

    public function getSelectedAdditionalItemsFromData(?array $data = null): array
    {
        if (! is_array($data) || ! isset($data['additional_items'])) {
            return [];
        }

        $selected = [];
        $additionalItemsData = $data['additional_items'];

        foreach ($additionalItemsData as $key => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity > 0) {
                $item = collect($this->getAdditionalItems())->firstWhere('key', $key);
                if ($item) {
                    $selected[] = [
                        'key' => $key,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'amount' => (float) $item['amount'],
                        'quantity' => $quantity,
                        'total_amount' => (float) $item['amount'] * $quantity,
                    ];
                }
            }
        }

        return $selected;
    }

    public function getTotalAdditionalItemsFromData(?array $data = null): float
    {
        return collect($this->getSelectedAdditionalItemsFromData($data))
            ->sum('total_amount');
    }

    public function getAmountWithAdditionalItemsFromData(?array $data = null): float
    {
        return (float) $this->amount + $this->getTotalAdditionalItemsFromData($data);
    }

    public function getAdditionalItemsTotal(?array $selectedData = null): float
    {
        return collect($this->resolveSelectedAdditionalItems($selectedData))
            ->sum('total_amount');
    }

    public function getAmountWithAdditionalItems(?array $selectedData = null): float
    {
        return (float) $this->amount + $this->getAdditionalItemsTotal($selectedData);
    }
}

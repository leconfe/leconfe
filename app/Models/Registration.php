<?php

namespace App\Models;

use App\Facades\Setting;
use App\Models\Concerns\BelongsToScheduledConference;
use App\Panel\ScheduledConference\Pages\Invoice;
use App\Panel\ScheduledConference\Pages\Receipt;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Plank\Metable\Metable;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Filament\Infolists\Components\Actions as InfolistActions;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\MaxWidth;
use Squire\Models\Country;
use Squire\Models\Currency;

class Registration extends Model implements HasMedia
{
    use HasFactory, Metable, BelongsToScheduledConference, InteractsWithMedia;

    protected $fillable = [
        'email',
        'given_name',
        'family_name',
        'cost',
        'currency',
        'type',
        'number',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'withdraw' => 'datetime',
    ];


    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn() => Str::squish($this->given_name . ' ' . $this->family_name),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    public function userSubmissions()
    {
        return $this->hasManyThrough(Submission::class, User::class, 'email', 'user_id', 'email', 'id');
    }

    public function getInfolistEntries(): array
    {
        return RegistrationForm::ordered()
            ->lazy()
            ->map(function (RegistrationForm $item) {
                return $this->getTextEntry($item);
            })
            ->toArray();
    }

    public function getTextEntry($item): TextEntry
    {
        $formEntries = collect($this->getMeta('form_entries'));

        return TextEntry::make($item->getFieldId())
            ->label($item->label)
            ->getStateUsing(fn() => match ($item->type) {
                RegistrationForm::TYPE_SELECT, RegistrationForm::TYPE_RADIO, RegistrationForm::TYPE_CHECKBOX => $item->getMeta('options')[$formEntries->get($item->getKey())] ?? '-',
                default => $formEntries->get($item->getKey()) ?? '-'
            });
    }

    public function countryName(): Attribute
    {
        return Attribute::make(
            get: fn(mixed $value, array $attributes) => Country::find($this->getMeta('country'))?->name
        );
    }

    public function detailInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this)
            ->schema([
                Grid::make()
                    ->columns(12)
                    ->schema([
                        Grid::make()
                            ->schema([
                                Section::make('Information')
                                    ->schema(fn($record) => [
                                        TextEntry::make('full_name'),
                                        TextEntry::make('email'),
                                        TextEntry::make('type'),
                                        TextEntry::make('cost')
                                            ->getStateUsing(fn(Registration $record) => money($record->cost, $record->currency, true)->formatWithoutZeroes()),
                                        TextEntry::make('currency')
                                            ->getStateUsing(fn(Registration $record) => Currency::find($record->currency)?->name),
                                        ...$record->getInfolistEntries(),
                                    ])
                                    ->headerActions([
                                        InfolistAction::make('proof_of_payment')
                                            ->label('Upload Proof of Payment')
                                            ->modalWidth(MaxWidth::ExtraLarge)
                                            ->fillForm([])
                                            ->visible(fn(Registration $record) => blank($record->paid_at))
                                            ->form([
                                                SpatieMediaLibraryFileUpload::make('payment_proof')
                                                    ->hiddenLabel()
                                                    ->disk('private-files')
                                                    ->collection('payment_proof'),
                                            ])
                                            ->action(function ($form, InfolistAction $action) {
                                                $action->successNotificationTitle('Payment Proof Uploaded');
                                                $action->success();
                                            }),
                                    ]),
                                Section::make('Address Information')
                                    ->schema([
                                        TextEntry::make('address_line')
                                            ->label('Address Line')
                                            ->state(fn(Registration $record) => $record->getMeta('address_line')),
                                        TextEntry::make('post_code')
                                            ->label('Postcode / ZIP Code')
                                            ->state(fn(Registration $record) => $record->getMeta('post_code')),
                                        TextEntry::make('city')
                                            ->label('City')
                                            ->state(fn(Registration $record) => $record->getMeta('city')),
                                        TextEntry::make('country')
                                            ->label('Country')
                                            ->state(function (Registration $record) {
                                                $country = Country::find($record->getMeta('country'));

                                                if (!$country) {
                                                    return '';
                                                }

                                                return $country->flag . ' ' . $country->name;
                                            }),
                                    ]),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 8,
                            ]),
                        Grid::make(1)
                            ->schema([
                                Section::make('Additional Information')
                                    ->headerActions([
                                        InfolistAction::make('edit-payment')
                                            ->label('Edit Payment')
                                            ->color('success')
                                            ->visible(fn() => auth()->user()->can('update', app()->getCurrentScheduledConference()))
                                            ->modalWidth(MaxWidth::ExtraLarge)
                                            ->fillForm(fn(Registration $record) => [
                                                'paid_at' => $record->paid_at,
                                                'paid_amount' => $record->getMeta('paid_amount') ?? $record->cost,
                                            ])
                                            ->form(
                                                fn(Registration $record, Form $form) => $form
                                                    ->schema([
                                                        DatePicker::make('paid_at')
                                                            ->label("Payment Date")
                                                            ->required()
                                                            ->default(now()),
                                                        TextInput::make('paid_amount')
                                                            ->label("Total Amount Paid")
                                                    ])
                                            )
                                            ->action(function (InfolistAction $action, array $data, Registration $record) {
                                                $record->paid_at = $data['paid_at'];
                                                $record->save();
                                                
                                                $record->setMeta('paid_amount', $data['paid_amount']);
                                                

                                                $action->successNotificationTitle('Payment Information Updated.');
                                                $action->success();
                                            }),
                                    ])
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Registered at')
                                            ->dateTime(Setting::get('format_date') . ' ' . Setting::get('format_time')),
                                        TextEntry::make('invoice')
                                            ->state('Download')
                                            ->color('primary')
                                            ->visible(fn() => app()->getCurrentScheduledConference()?->isInvoiceEnabled())
                                            ->url(fn(Registration $record) => Invoice::getUrl(['record' => $record]))
                                            ->openUrlInNewTab(),
                                        TextEntry::make('proof_of_payment')
                                            ->state('Download')
                                            ->visible(fn(Registration $record) => $record->hasMedia('payment_proof'))
                                            ->color('primary')
                                            ->action(
                                                InfolistAction::make('download')
                                                    ->link()
                                                    ->visible(fn(Registration $record) => $record->hasMedia('payment_proof'))
                                                    ->action(fn(Registration $record) => $record->getFirstMedia('payment_proof')),
                                            ),
                                        TextEntry::make('paid_at')
                                            ->visible(fn(Registration $record) => $record->paid_at)
                                            ->dateTime(Setting::get('format_date')),
                                        TextEntry::make('receipt')
                                            ->state('Download')
                                            ->color('primary')
                                            ->visible(fn(Registration $record) => app()->getCurrentScheduledConference()?->isReceiptEnabled() && $record->paid_at)
                                            ->url(fn(Registration $record) => Receipt::getUrl(['record' => $record]))
                                            ->openUrlInNewTab(),
                                    ]),
                                Section::make('Submissions')
                                    ->visible(fn($record) => $record->userSubmissions->isNotEmpty())
                                    ->schema([
                                        RepeatableEntry::make('userSubmissions')
                                            ->hiddenLabel()
                                            ->schema([
                                                TextEntry::make('title')
                                                    ->url(fn(Submission $record) => SubmissionResource::getUrl('view', ['record' => $record]))
                                                    ->hiddenLabel()
                                                    ->color('primary')
                                                    ->openUrlInNewTab()
                                                    ->getStateUsing(fn(Submission $record) => $record->getMeta('title') ?? '-'),
                                            ])
                                            ->contained(false)
                                    ]),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 4,
                            ]),
                    ])

            ]);
    }
}

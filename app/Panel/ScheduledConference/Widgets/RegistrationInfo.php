<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Facades\Setting;
use App\Models\Registration;
use App\Models\Submission;
use App\Panel\ScheduledConference\Pages\ParticipantRegistration;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets\Widget;
use Squire\Models\Country;
use Squire\Models\Currency;

class RegistrationInfo extends Widget implements HasInfolists, HasForms
{
    use InteractsWithInfolists, InteractsWithForms;

    protected static string $view = 'panel.scheduledConference.widgets.registration-info';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return app()->getCurrentScheduledConference()->isRegistrationOpen();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'isRegiteredAsParticipant' => $user->isRegisteredAsParticipant(),
            'registerAsParticipantUrl' => ParticipantRegistration::getUrl(),
            // 'registrationDetailUrl' => RegistrationDetail::getUrl(),
            'conferenceTitle' => app()->getCurrentScheduledConference()?->title,
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $record = Registration::query()
            ->where('email', auth()->user()->email)
            ->first();

        return $infolist
            ->record($record)
            ->schema([
                Grid::make()
                    ->columns(12)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Section::make('Registration Information')
                                    ->schema([
                                        TextEntry::make('full_name'),
                                        TextEntry::make('email'),
                                        TextEntry::make('affiliation')
                                            ->state(fn(Registration $record) => $record->getMeta('affiliation')),
                                        TextEntry::make('type'),
                                        TextEntry::make('cost')
                                            ->state(fn(Registration $record) => money($record->cost, $record->currency, true)->formatWithoutZeroes()),
                                        TextEntry::make('currency')
                                            ->state(fn(Registration $record) => Currency::find($record->currency)?->name),
                                        ...$record->getInfolistEntries(),
                                    ])
                                    ->headerActions([
                                        Action::make('proof_of_payment')
                                            ->label('Upload Proof of Payment')
                                            ->modalWidth(MaxWidth::ExtraLarge)
                                            ->fillForm([])
                                            ->form([
                                                SpatieMediaLibraryFileUpload::make('payment_proof')
                                                    ->hiddenLabel()
                                                    ->disk('private-files')
                                                    ->collection('payment_proof'),
                                            ])
                                            ->action(function ($form, Action $action) {
                                                $action->successNotificationTitle('Payment Proof Uploaded');
                                                $action->success();
                                            }),
                                        Action::make('invoice')
                                            ->label('Download Invoice')
                                            ->color('gray')
                                            ->action(fn() => dd($this))
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
                                Section::make()
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Registered At')
                                            ->dateTime(Setting::get('format_date') . ' ' . Setting::get('format_time')),
                                        TextEntry::make('paid_at')
                                            ->visible(fn(Registration $record) => $record->paid_at),
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
                    ]),
            ]);
    }
}

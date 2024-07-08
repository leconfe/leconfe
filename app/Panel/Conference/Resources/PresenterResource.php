<?php

namespace App\Panel\Conference\Resources;

use Filament\Tables;
use App\Models\Timeline;
use Filament\Forms\Form;
use App\Models\Presenter;
use Filament\Tables\Table;
use Squire\Models\Country;
use App\Models\MailTemplate;
use App\Models\Enums\UserRole;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Tables\Grouping\Group;
use Illuminate\Support\Facades\Mail;
use App\Models\Enums\PresenterStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Illuminate\Database\Eloquent\Builder;
use App\Mail\Templates\ApprovedPresenterMail;
use App\Mail\Templates\RejectedPresenterMail;
use App\Actions\Presenters\PresenterApprovedAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use App\Panel\Conference\Resources\PresenterResource\Pages;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class PresenterResource extends Resource
{
    protected static ?string $model = Presenter::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('translation.presenter.presenterResourceLabelTitlePresenters');
    }
    
    public static function getModelLabel(): string
    {
        return __('translation.presenter.presenterResourceLabelTitlePresenters');
    }



    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()
            ->with(['media', 'meta', 'submission.participants'])
            ->whereHas(
                'submission',
                fn ($query) => $query->where('conference_id', app()->getCurrentConference()->getKey())
            )
            ->whereHas(
                'submission.participants',
                fn ($query) => $query->where('user_id', auth()->user()->getKey())
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup('submission.id')
            ->groups([
                Group::make('submission.id')
                    ->label(__('translation.presenter.presenterResourceLabelGroupBySubmission'))
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (Presenter $record): string => 'Submission : '.ucfirst($record->submission?->getMeta('title')))
                    ->getDescriptionFromRecordUsing(fn (Presenter $record): string => 'Status : '.$record->submission->status->value)
            ])
            ->columns([
                Split::make([
                    SpatieMediaLibraryImageColumn::make('profile')
                        ->grow(false)
                        ->collection('profile')
                        ->conversion('avatar')
                        ->width(50)
                        ->height(50)
                        ->defaultImageUrl(
                            fn (Model $record): string => $record->getFilamentAvatarUrl()
                        )
                        ->extraCellAttributes([
                            'style' => 'width: 1px',
                        ])
                        ->circular(),
                    Stack::make([
                        TextColumn::make('fullName')
                            ->suffix(function (Model $record) {
                                $country = Country::find($record->getMeta('country'));
                                
                                return $country ? new HtmlString(<<<HTML
                                    <span x-tooltip.raw="{$country->name}"> {$country->flag}</span>
                                HTML) : null;
                            })
                            ->formatStateUsing(function (Model $record) {
                                if ($record->email == auth()->user()->email) {
                                    return $record->fullName.' (You)';
                                }

                                return $record->fullName;
                            })
                            ->searchable(
                                query: fn ($query, $search) => $query
                                    ->whereMeta('country', 'LIKE', "%{$search}%")
                                    ->orWhere('given_name', 'LIKE', "%{$search}%")
                                    ->orWhere('family_name', 'LIKE', "%{$search}%")
                            ),
                        Split::make([
                            TextColumn::make('affiliation')
                                ->size('xs')
                                ->getStateUsing(
                                    fn (Model $record) => $record->getMeta('affiliation')
                                )
                                ->icon('heroicon-o-building-library')
                                ->searchable(
                                    query: fn ($query, $search) => $query
                                        ->whereMeta('affiliation', 'LIKE', "%{$search}%")
                                )
                                ->extraAttributes([
                                    'class' => 'text-xs',
                                ])
                                ->color('gray'),
                            TextColumn::make('email')
                                ->size('xs')
                                ->extraAttributes([
                                    'class' => 'text-xs',
                                ])
                                ->searchable()
                                ->color('gray')
                                ->icon('heroicon-o-envelope')
                                ->alignStart(),
                        ])
                        ->extraAttributes(fn (Model $record) => [
                            'class' => $record->getMeta('affiliation') ? 'w-fit' : 'w-fit !gap-0'
                        ]),
                        Panel::make([
                            TextColumn::make('notes')
                                ->label(__('translation.presenter.PresenterResourceLabelNotes'))
                                ->size('xs')
                                ->getStateUsing(function (Presenter $record) {
                                    return new HtmlString(<<<HTML
                                        <div class="space-y-1 text-xs">
                                            <p class="text-gray-500">Notes :</p>
                                            <p class="text-gray-700">{$record->getMeta('notes')}</p>
                                        </div>
                                    HTML);
                                })
                        ])
                        ->visible(fn (Model $record) => $record->getMeta('notes') && $record->status != PresenterStatus::Unchecked)
                        ->collapsible()
                        ->extraAttributes([
                            'class' => 'w-1/2',
                        ]),
                    ])
                    ->space(1),
                ]),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    ...static::getTableActions(),
                ])
                ->visible(fn (Presenter $record) => ! $record->submission->isPublished())
                ->label(fn (Presenter $record) => $record->status == PresenterStatus::Unchecked ? 'Set Decision' : $record->status->getActionText())
                ->icon(fn (Presenter $record) => $record->status->getActionIcon())
                ->color(fn (Presenter $record) => $record->status->getActionColor())
                ->button()
                ->outlined()
            ]);
    }

    public static function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('approve')
                ->label(__('translation.presenter.presenterResourceLabelApprove'))
                ->modalHeading(__('translation.presenter.presenterResourceModalHeadingApprove'))
                ->icon('heroicon-o-check')
                ->iconSize('md')
                ->color('primary')
                ->hidden(fn (Presenter $record) => $record->status == PresenterStatus::Approve)
                ->mountUsing(function (Form $form): void {
                    $mailTemplate = MailTemplate::where('mailable', ApprovedPresenterMail::class)->first();
                    $form->fill([
                        'notes' => $mailTemplate ? $mailTemplate->html_template : '',
                    ]);
                })
                ->form([
                    Select::make('set_date')
                        ->label(__('translation.presenter.presenterResourceLabelSetDate'))
                        ->required()
                        ->preload()
                        ->searchable()
                        ->options(function () {
                            return Timeline::query()
                                ->whereJsonContains('roles', UserRole::Presenter)
                                ->get()
                                ->mapWithKeys(fn (Timeline $timeline) => [$timeline->id => $timeline->date->isoFormat('D MMM Y').' - '.$timeline->title])
                                ->toArray();
                        }),
                    TinyEditor::make('notes')
                        ->label(__('translation.presenter.presenterResourceLabelNotes'))
                        ->required()
                        ->placeholder(__('translation.presenter.presenterResourcePlaceholderEnterYourNotes'))
                        ->helperText(__('translation.presenter.presenterResourceHelperTextNote'))
                        ->profile('email'),
                    Checkbox::make('do-not-notify-presenter')
                        ->label(__('translation.presenter.presenterResourceLabelCheckBox')),
                ])
                ->successNotificationTitle(__('translation.presenter.presenterResourcesuccessNotificationTitleApproved'))
                ->action(function (Tables\Actions\Action $action, array $data, Presenter $record) {
                    $approvedPresenter = PresenterApprovedAction::run($record);
                            
                    $mailTemplate = MailTemplate::where('mailable', ApprovedPresenterMail::class)->first();
                    $getTemplateMail = (new ApprovedPresenterMail($approvedPresenter))
                        ->subjectUsing($mailTemplate->subject)
                        ->contentUsing($data['notes']);

                    $approvedPresenter->setManyMeta([
                        'notes' => $getTemplateMail->message,
                        'approved_by' => auth()->user()->id,
                        'approved_at' => now()->toDateTimeString(),
                        'timeline_date' => Timeline::find($data['set_date'])->date,
                    ]);

                    if (! $data['do-not-notify-presenter']) {
                        try {
                            Mail::to($record->email)
                                ->send($getTemplateMail);
                        } catch (\Exception $e) {
                            $action->failureNotificationTitle(__('translation.presenter.presenterResourceFailureNotificationTitleEmail'));
                            $action->failure();
                        }
                    }

                    $action->success();
                }),
                Tables\Actions\Action::make('reject')
                    ->label(__('translation.presenter.presenterResourceLabelReject'))
                    ->modalHeading(__('translation.presenter.presenterResourceModalHeadingRejectPresenter'))
                    ->icon('heroicon-o-x-mark')
                    ->iconSize('md')
                    ->color('danger')
                    ->hidden(fn (Presenter $record) => $record->status == PresenterStatus::Reject)
                    ->mountUsing(function (Form $form): void {
                        $mailTemplate = MailTemplate::where('mailable', RejectedPresenterMail::class)->first();
                        $form->fill([
                            'notes' => $mailTemplate ? $mailTemplate->html_template : '',
                        ]);
                    })
                    ->form([
                        TinyEditor::make('notes')
                            ->label(__('translation.presenter.presenterResourceLabelNotes'))
                            ->profile('email')
                            ->required()
                            ->placeholder(__('translation.presenter.presenterResourceplaceholderEnterYoutNote'))
                            ->helperText(__('translation.presenter.presenterResourceHelperTextThisNoteWill')),
                        Checkbox::make('do-not-notify-presenter')
                            ->label(__('translation.presenter.presenterResourceLabelCheckBox')),
                    ])
                    ->successNotificationTitle(__('translation.presenter.presenterResourcesuccessNotificationTitleRejected'))
                    ->action(function (Tables\Actions\Action $action, array $data, Presenter $record) {
                        $rejectedPresenter = PresenterRejectedAction::run($record);
                            
                        $mailTemplate = MailTemplate::where('mailable', RejectedPresenterMail::class)->first();
                        $getTemplateMail = (new RejectedPresenterMail($rejectedPresenter))
                            ->subjectUsing($mailTemplate->subject)
                            ->contentUsing($data['notes']);

                        $rejectedPresenter->setManyMeta([
                            'notes' => $getTemplateMail->message,
                            'rejected_by' => auth()->user()->id,
                        ]);

                        if (! $data['do-not-notify-presenter']) {
                            try {
                                Mail::to($record->email)
                                    ->send($getTemplateMail);
                            } catch (\Exception $e) {
                                $action->failureNotificationTitle(__('translation.presenter.presenterResourceFailureNotificationTitleEmail'));
                                $action->failure();
                            }
                        }

                        $action->success();
                    })
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePresenters::route('/'),
        ];
    }
}
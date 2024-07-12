<?php

namespace App\Panel\Conference\Resources;

use App\Actions\User\UserDeleteAction;
use App\Actions\User\UserMailAction;
use App\Actions\User\UserUpdateAction;
use App\Facades\Setting;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use App\Panel\Conference\Resources\UserResource\Pages;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    // protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationLabel(): string
   {
       return __('translation.userResource.getNavigationLabelUser');
   }

    public static function getModelLabel(): string
   {
       return __('translation.userResource.getNavigationLabelUser');
   }

    public static function getNavigationGroup(): string
    {
        return __('translation.pluginResource.navigationGroupTitle');
    }

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()
            ->whereHas('roles')
            ->with(['meta', 'media', 'bans']);
    }

    public static function isDiscovered(): bool
    {
        return static::$isDiscovered;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(3)
            ->schema([
                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\SpatieMediaLibraryFileUpload::make('profile')
                                    ->label(__('translation.userResource.labelProfilePhoto'))
                                    ->collection('profile')
                                    ->alignCenter()
                                    ->avatar()
                                    ->columnSpan(['lg' => 2]),
                                Forms\Components\TextInput::make('given_name')
                                    ->label(__('translation.userResource.labelGivenName'))
                                    ->required(),
                                Forms\Components\TextInput::make('family_name')
                                    ->label(__('translation.userResource.labelFamilyName')),
                                Forms\Components\TextInput::make('email')
                                    ->columnSpan(['lg' => 2])
                                    ->disabled(fn (?User $record) => $record)
                                    ->dehydrated(fn (?User $record) => !$record)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\TextInput::make('password')
                                    ->required(fn (?User $record) => !$record)
                                    ->password()
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->label(__('translation.userResource.labelPassword'))
                                    ->confirmed(),
                                Forms\Components\TextInput::make('password_confirmation')
                                    ->requiredWith('password')
                                    ->password()
                                    ->dehydrated(false)
                                    ->label(__('translation.userResource.labelPasswordConfirmation')),
                                ...ContributorForm::additionalFormField(),
                            ])
                            ->columns(2),

                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Placeholder::make('created_at')
                                    ->label(__('translation.userResource.labelCreatedAt'))
                                    ->content(fn (?User $record): ?string => $record?->created_at?->diffForHumans() ?? '-'),

                                Forms\Components\Placeholder::make('updated_at')
                                    ->label(__('translation.userResource.labelLastModifiedAt'))
                                    ->content(fn (?User $record): ?string => $record?->updated_at?->diffForHumans() ?? '-'),

                                Forms\Components\Placeholder::make('disabled_at')
                                    ->visible(fn (?User $record) => $record?->isBanned())
                                    ->label(__('translation.userResource.labelDisabledAt'))
                                    ->content(function (?User $record): ?string {
                                        $ban = $record?->bans->first();

                                        return $ban?->created_at?->format(Setting::get('format_date')) ?? '-';
                                    }),
                                Forms\Components\Placeholder::make('disabled_until')
                                    ->visible(fn (?User $record) => $record?->isBanned())
                                    ->label(__('translation.userResource.labelDisabledUntil'))
                                    ->content(function (?User $record): ?string {
                                        $ban = $record?->bans->first();

                                        return $ban?->expired_at?->format(Setting::get('format_date')) ?? '-';
                                    }),

                            ]),
                        Forms\Components\Section::make(__('translation.userResource.labelUserRoles'))
                            ->schema([
                                Forms\Components\CheckboxList::make('roles')
                                    ->label('')
                                    ->relationship(
                                        name: 'roles',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query) => $query->where('name', '!=', UserRole::Admin)
                                    )
                                    ->saveRelationshipsUsing(function (Forms\Components\CheckboxList $component, ?array $state) {
                                        $roles = $state ? Role::whereIn('id', $state)->pluck('name')->toArray() : [];

                                        $component->getModelInstance()->syncRoles($roles);
                                    }),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    SpatieMediaLibraryImageColumn::make('profile')
                        ->grow(false)
                        ->label(__('translation.userResource.labelProfile'))
                        ->collection('profile')
                        ->conversion('avatar')
                        ->width(50)
                        ->height(50)
                        ->defaultImageUrl(function (User $record): string {
                            $name = Str::of(Filament::getUserName($record))
                                ->trim()
                                ->explode(' ')
                                ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
                                ->join(' ');

                            return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&color=FFFFFF&background=111827&font-size=0.33';
                        })
                        ->extraCellAttributes([
                            'style' => 'width: 1px',
                        ])
                        ->circular(),
                    Stack::make([
                        TextColumn::make('full_name')
                            ->label(__('translation.userResource.labelFullName'))
                            ->weight(FontWeight::Medium)
                            ->searchable(
                                query: fn ($query, $search) => $query
                                    ->where('given_name', 'LIKE', "%{$search}%")
                                    ->orWhere('family_name', 'LIKE', "%{$search}%")
                            )
                            ->sortable(
                                query: fn ($query, $direction) => $query
                                    ->orderBy('given_name', $direction)
                                    ->orderBy('family_name', $direction)
                            ),
                        TextColumn::make('email')
                            ->wrap()
                            ->color('gray')
                            ->searchable()
                            ->size('sm')
                            ->sortable()
                            ->icon('heroicon-m-envelope'),
                        TextColumn::make('affiliation')
                            // ->color(Color::hex('#A6CE39'))
                            ->label(__('translation.userResource.labelAffiliation'))
                            ->size('sm')
                            ->wrap()
                            ->color('gray')
                            ->icon('heroicon-s-building-library')
                            ->getStateUsing(fn (User $record) => $record->getMeta('affiliation')),
                        TextColumn::make('disabled')
                            ->getStateUsing(function (User $record) {
                                if (!$record->isBanned()) {
                                    return null;
                                }

                                $ban = $record->bans->filter(function ($ban) {
                                    return $ban->notExpired();
                                })->first();

                                $bannedUntil = $ban->expired_at;

                                return 'Disabled' . ($bannedUntil ? " until {$bannedUntil->format(Setting::get('format_date'))}" : '');
                            })
                            ->color('danger')
                            ->badge(),
                    ]),
                    Stack::make([
                        TextColumn::make('roles.name')
                            ->badge(),
                    ]),
                    // Stack::make([
                    //     TextColumn::make('phone')
                    //         ->icon('heroicon-m-phone')
                    //         ->getStateUsing(fn (User $record) => $record->getMeta('phone')),
                    //     TextColumn::make('orcid_id')
                    //         ->color(Color::hex('#A6CE39'))
                    //         ->icon('academicon-orcid')
                    //         ->getStateUsing(fn (User $record) => $record->getMeta('orcid_id')),
                    //     TextColumn::make('google_scholar')
                    //         ->color(Color::hex('#4081EC'))
                    //         ->icon('academicon-google-scholar')
                    //         ->getStateUsing(fn (User $record) => $record->getMeta('google_scholar_id')),
                    //     TextColumn::make('scopus_id')
                    //         ->color(Color::hex('#e9711c'))
                    //         ->icon('academicon-scopus-square')
                    //         ->getStateUsing(fn (User $record) => $record->getMeta('scopus_id')),
                    // ])
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth('full')
                    ->mutateRecordDataUsing(fn ($data, User $record) => array_merge($data, ['meta' => $record->getAllMeta()->toArray()]))
                    ->using(fn (array $data, User $record) => UserUpdateAction::run($data, $record)),
                DeleteAction::make()
                    ->using(fn (?array $data, User $record) => UserDeleteAction::run($data, $record)),
                ActionGroup::make([
                    Impersonate::make()
                        ->grouped()
                        ->hidden(fn ($record) => !auth()->user()->can('loginAs', $record))
                        ->label(fn (User $record) => __('translation.userResource.labelLoginAs') . ' ' . $record->given_name)
                        ->icon('heroicon-m-key')
                        ->color('primary')
                        ->redirectTo(fn () => route('filament.conference.pages.dashboard')),
                    Action::make('enable')
                        ->visible(fn (User $record) => auth()->user()->can('enable', $record))
                        ->label(fn (User $record) => __('translation.userResource.labelEnableUser'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (User $record) {
                            $record->unban();
                        }),
                    Action::make('disable')
                        ->visible(fn (User $record) => auth()->user()->can('disable', $record))
                        ->label(fn (User $record) => __('translation.userResource.labelDisable'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->modalWidth('xl')
                        ->modalHeading(fn (User $record) => __('translation.userResource.labelDisableUser') . ' ' . $record->full_name)
                        ->form([
                            Textarea::make('comment')
                                ->label(__('translation.userResource.labelReasonForDisablingUser')),
                            DatePicker::make('expired_at')
                                ->label(__('translation.userResource.labelUntil'))
                                ->minDate(now()->addDay())
                                ->hint(__('translation.userResource.hintaToDisablePermanently')),
                        ])
                        ->action(function (array $data, User $record) {
                            $record->ban($data);
                        }),
                    Action::make('email')
                        ->visible(fn (User $record) => auth()->user()->can('sendEmail', $record))
                        ->label(fn (User $record) => __('translation.userResource.labelSendEmail'))
                        ->icon('heroicon-o-envelope')
                        ->modalWidth('3xl')
                        ->fillForm(fn ($record) => ['to' => $record->email])
                        ->modalHeading(fn (User $record) => __('translation.userResource.modalHeadingSendEmailTo') ." ".$record->full_name)
                        ->form([
                            Grid::make()
                                ->schema([
                                    TextInput::make('subject')
                                        ->label(__('translation.userResource.labelSubject'))
                                        ->required(),
                                    TextInput::make('to')
                                        ->label(__('translation.userResource.labelTo'))
                                        ->disabled()
                                        ->required(),
                                ]),
                            TinyEditor::make('message')
                                ->label(__('translation.userResource.labelMessage'))
                                ->minHeight(500)
                                ->required(),
                        ])
                        ->action(function (User $record, array $data) {
                            UserMailAction::run($record, ...Arr::only($data, ['subject', 'message']));
                        }),

                ]),
            ])
            ->queryStringIdentifier('users')
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Panel\Conference\Resources;

use App\Actions\User\UserDeleteAction;
use App\Actions\User\UserMailAction;
use App\Facades\Setting;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Forms\Components\TinyEditor;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    public static function getNavigationLabel(): string
    {
        return __('general.user');
    }

    public static function getModelLabel(): string
    {
        return __('general.user');
    }

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()
            ->with(['meta', 'media', 'bans'])
            ->when(!app()->isOnSite(), fn(Builder $query) => $query->where(function (Builder $query) {
                $query
                    ->where('id', auth()->id())
                    ->orWhereDoesntHave('roles', fn(Builder $query) => $query->where('name', UserRole::Admin));
            }));
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
                                SpatieMediaLibraryFileUpload::make('profile')
                                    ->label(__('general.profile_photo'))
                                    ->collection('profile')
                                    ->alignCenter()
                                    ->avatar()
                                    ->columnSpan(['lg' => 2]),
                                Forms\Components\TextInput::make('given_name')
                                    ->label(__('general.given_name'))
                                    ->required(),
                                Forms\Components\TextInput::make('family_name')
                                    ->label(__('general.family_name')),
                                Forms\Components\TextInput::make('meta.public_name')
                                    ->label(__('general.public_name'))
                                    ->helperText(__('general.public_name_helper'))
                                    ->columnSpan(['lg' => 2]),
                                Forms\Components\TextInput::make('email')
                                    ->required()
                                    ->label(__('general.email'))
                                    ->columnSpan(['lg' => 2])
                                    ->unique(ignoreRecord: true),
                                Forms\Components\TextInput::make('password')
                                    ->label(__('general.password'))
                                    ->required(fn(?User $record) => !$record)
                                    ->password()
                                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                                    ->dehydrated(fn($state) => filled($state))
                                    ->confirmed(),
                                Forms\Components\TextInput::make('password_confirmation')
                                    ->label(__('general.password_confirmation'))
                                    ->requiredWith('password')
                                    ->password()
                                    ->minLength(12)
                                    ->dehydrated(false),
                                ...ContributorForm::additionalFormField(),
                            ])
                            ->columns(2),

                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->visible(fn(?User $record) => $record?->isBanned())
                            ->schema([
                                Forms\Components\Placeholder::make('disabled_at')
                                    ->visible(fn(?User $record) => $record?->isBanned())
                                    ->label(__('general.disabled_at'))
                                    ->content(function (?User $record): ?string {
                                        $ban = $record?->bans->first();

                                        return $ban?->created_at?->format(Setting::get('format_date')) ?? '-';
                                    }),
                                Forms\Components\Placeholder::make('disabled_until')
                                    ->visible(fn(?User $record) => $record?->isBanned())
                                    ->label(__('general.disabled_until'))
                                    ->content(function (?User $record): ?string {
                                        $ban = $record?->bans->first();

                                        return $ban?->expired_at?->format(Setting::get('format_date')) ?? '-';
                                    }),

                            ]),
                        Forms\Components\Section::make(__('general.user_roles'))
                            ->hidden(fn() => app()->isOnSite())
                            ->schema([
                                Forms\Components\CheckboxList::make('roles')
                                    ->hiddenLabel()
                                    ->required()
                                    ->relationship(
                                        name: 'roles',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn($query) => $query->withoutGlobalScopes()->availableRolesByContext()
                                    )
                                    ->saveRelationshipsUsing(function (Forms\Components\CheckboxList $component, ?array $state, User $record) {

                                        $roles = $state ? Role::whereIn('id', $state)->pluck('name')->toArray() : [];

                                        $roles = array_diff($roles, [UserRole::Admin->value]);

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
                        ->collection('profile')
                        ->conversion('avatar')
                        ->width(50)
                        ->height(50)
                        ->defaultImageUrl(function (User $record): string {
                            $name = Str::of(Filament::getUserName($record))
                                ->trim()
                                ->explode(' ')
                                ->map(fn(string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
                                ->join(' ');

                            return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&color=FFFFFF&background=111827&font-size=0.33';
                        })
                        ->extraCellAttributes([
                            'style' => 'width: 1px',
                        ])
                        ->circular(),
                    Stack::make([
                        TextColumn::make('full_name')
                            ->weight(FontWeight::Medium)
                            ->searchable(
                                query: fn($query, $search) => $query
                                    ->whereMeta('public_name', 'LIKE', "%{$search}%")
                                    ->orWhere('given_name', 'LIKE', "%{$search}%")
                                    ->orWhere('family_name', 'LIKE', "%{$search}%")
                            ),
                        TextColumn::make('email')
                            ->wrap()
                            ->color('gray')
                            ->searchable()
                            ->size('sm')
                            ->sortable()
                            ->icon('heroicon-m-envelope'),
                        TextColumn::make('affiliation')
                            ->size('sm')
                            ->wrap()
                            ->color('gray')
                            ->icon('heroicon-s-building-library')
                            ->searchable(
                                query: fn($query, $search) => $query
                                    ->whereMeta('affiliation', 'LIKE', "%{$search}%")
                            )
                            ->getStateUsing(fn(User $record) => $record->getMeta('affiliation')),
                        TextColumn::make('disabled')
                            ->getStateUsing(function (User $record) {
                                if (!$record->isBanned()) {
                                    return null;
                                }

                                $ban = $record->bans->filter(function ($ban) {
                                    return $ban->notExpired();
                                })->first();

                                $bannedUntil = $ban->expired_at;

                                return __('general.disabled') . ($bannedUntil ? __('general.until') . $bannedUntil->format(Setting::get('format_date')) : '');
                            })
                            ->color('danger')
                            ->badge(),
                    ]),
                    Stack::make([
                        TextColumn::make('roles.name')
                            ->badge(),
                    ]),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('general.roles'))
                    ->relationship('roles', 'name', modifyQueryUsing: fn($query) => $query->withoutGlobalScopes()->availableRolesByContext())
                    ->multiple()
                    ->preload(),
            ])
            ->deferFilters()
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-user-plus'),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth('full'),
                Action::make('remove')
                    ->color('danger')
                    ->icon('heroicon-m-trash')
                    ->requiresConfirmation()
                    ->authorize('delete')
                    ->hidden(fn() => app()->isOnSite())
                    ->visible(fn($record) => $record->roles->count() && app()->isOnSite())
                    ->action(function (User $record, Action $action) {
                        $result = $record->syncRoles([]);

                        if (!$result) {
                            $action->failure();

                            return;
                        }

                        $action->success();
                    }),
                ActionGroup::make([
                    Impersonate::make()
                        ->grouped()
                        ->hidden(fn($record) => !auth()->user()->can('loginAs', $record))
                        ->label(fn(User $record) => __('general.login_as_user', ['name' => $record->full_name]))
                        ->icon('heroicon-m-key')
                        ->color('primary')
                        ->redirectTo(fn() => app()->getCurrentScheduledConference()?->getPanelUrl() ?? app()->getCurrentConference()?->getPanelUrl()),
                    Action::make('email')
                        ->visible(fn(User $record) => auth()->user()->can('sendEmail', $record))
                        ->label(fn(User $record) => __('general.send_email'))
                        ->icon('heroicon-o-envelope')
                        ->modalWidth('3xl')
                        ->fillForm(fn($record) => ['to' => $record->email])
                        ->modalHeading(fn(User $record) => __('general.send_email_to') . $record->full_name)
                        ->form([
                            Grid::make()
                                ->schema([
                                    TextInput::make('subject')
                                        ->label(__('general.subject'))
                                        ->required(),
                                    TextInput::make('to')
                                        ->label(__('general.to'))
                                        ->disabled()
                                        ->required(),
                                ]),
                            TinyEditor::make('message')
                                ->label(__('general.message'))
                                ->minHeight(500)
                                ->required(),
                        ])
                        ->action(function (User $record, array $data) {
                            UserMailAction::run($record, ...Arr::only($data, ['subject', 'message']));
                        }),
                    Action::make('enable')
                        ->visible(fn(User $record) => auth()->user()->can('enable', $record))
                        ->label(__('general.enable_user'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (User $record) {
                            $record->unban();
                        }),
                    Action::make('disable')
                        ->visible(fn(User $record) => auth()->user()->can('disable', $record))
                        ->label(fn(User $record) => __('general.disable'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->modalWidth('xl')
                        ->modalHeading(fn(User $record) => "Disable User : {$record->full_name}")
                        ->form([
                            Textarea::make('comment')
                                ->label(__('general.reason_for_disabling_user')),
                            DatePicker::make('expired_at')
                                ->label(__('general.until'))
                                ->minDate(now()->addDay())
                                ->hint(__('general.to_disable_permanently_leave_field_empty')),
                        ])
                        ->action(function (array $data, User $record) {
                            $record->ban($data);
                        }),
                    DeleteAction::make()
                        ->visible(fn() => app()->isOnSite())
                        ->using(function (?array $data, User $record, DeleteAction $action) {
                            try {
                                $user = UserDeleteAction::run($data, $record);

                                return $user;
                            } catch (\Throwable $th) {
                                $action->failureNotificationTitle($th->getMessage());

                                return false;
                            }
                        }),
                ]),
            ])
            ->queryStringIdentifier('users')
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-user-plus'),
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

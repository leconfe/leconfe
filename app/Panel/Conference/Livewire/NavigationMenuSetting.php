<?php

namespace App\Panel\Conference\Livewire;

use App\Actions\NavigationMenu\CreateNavigationMenuAction;
use App\Actions\NavigationMenu\CreateNavigationMenuItemAction;
use App\Actions\NavigationMenu\UpdateNavigationMenuAction;
use App\Actions\NavigationMenu\UpdateNavigationMenuItemAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;
use Livewire\Component;

class NavigationMenuSetting extends Component implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    public function render()
    {
        return view('panel.conference.livewire.navigation-menu', $this->getViewData());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'navigationMenus' => NavigationMenu::query()
                ->when(! app()->getCurrentScheduledConferenceId(), function ($query) {
                    $query->where('scheduled_conference_id', 0);
                })
                ->with([
                    'items' => function ($query) {
                        $query
                            ->ordered()
                            ->whereNull('parent_id')
                            ->with('children', function ($query) {
                                $query->ordered();
                            });
                    },
                ])
                ->orderBy('id')
                ->get(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create-navigation-menu')
                ->label(__('general.add_navigation_menu'))
                ->modalWidth('xl')
                ->form($this->getNavigationMenuForm())
                ->action(function ($data) {
                    CreateNavigationMenuAction::run($data);
                }),
        ];
    }

    public function editNavigationMenuAction(): Action
    {
        return Action::make('editNavigationMenuAction')
            ->label(__('general.edit'))
            ->modalWidth('xl')
            ->icon('heroicon-s-pencil')
            ->size('xs')
            ->form($this->getNavigationMenuForm())
            ->fillForm(function (array $arguments) {
                return NavigationMenu::query()
                    ->select(['name', 'handle'])
                    ->where('id', $arguments['id'])
                    ->first()
                    ->toArray();
            })
            ->action(function (array $data, array $arguments) {
                UpdateNavigationMenuAction::run(NavigationMenu::find($arguments['id']), $data);
            });
    }

    public function deleteNavigationMenuAction(): Action
    {
        return Action::make('deleteNavigationMenuAction')
            ->label(__('general.delete'))
            ->icon('heroicon-s-trash')
            ->color('danger')
            ->size('xs')
            // ->extraAttributes([
            //     'class' => 'hidden'
            // ])
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                NavigationMenu::destroy($arguments['id']);
            });
    }

    public function addNavigationMenuItemAction(): Action
    {
        return Action::make('addNavigationMenuItemAction')
            ->label(__('general.add_item'))
            ->modalHeading(__('general.add_navigation_menu_item'))
            ->icon('heroicon-s-plus')
            ->size('xs')
            ->color('gray')
            ->modalWidth('xl')
            ->form($this->getNavigationMenuItemForm())
            ->action(function (array $data, array $arguments) {
                $data['navigation_menu_id'] = $arguments['navigation_menu_id'];

                CreateNavigationMenuItemAction::run($data);
            });
    }

    public function addNavigationMenuItemChildAction(): Action
    {
        return Action::make('addNavigationMenuItemChildAction')
            ->label(__('general.add_item'))
            ->modalHeading(__('general.add_navigation_menu_item_child'))
            ->icon('heroicon-s-plus')
            ->size('xs')
            ->color('gray')
            ->extraAttributes([
                'class' => 'hidden',
            ])
            ->modalWidth('xl')
            ->form($this->getNavigationMenuItemForm())
            ->action(function (array $data, array $arguments) {
                $data['parent_id'] = $arguments['parent_id'];
                $data['navigation_menu_id'] = $arguments['navigation_menu_id'];

                CreateNavigationMenuItemAction::run($data);
            });
    }

    public function editNavigationMenuItemAction(): Action
    {
        return Action::make('editNavigationMenuItemAction')
            ->label(__('general.edit_item'))
            ->modalHeading(__('general.edit_navigation_menu_item'))
            ->extraAttributes([
                'class' => 'hidden',
            ])
            ->fillForm(function (array $arguments) {
                $navigationMenuItem = NavigationMenuItem::find($arguments['id']);

                return [
                    ...$navigationMenuItem->toArray(),
                    'meta' => $navigationMenuItem->getAllMeta(),
                ];
            })
            ->color('gray')
            ->modalWidth('xl')
            ->form($this->getNavigationMenuItemForm())
            ->action(function (array $data, array $arguments) {
                UpdateNavigationMenuItemAction::run(NavigationMenuItem::find($arguments['id']), $data);
            });
    }

    public function deleteNavigationItemMenuAction(): Action
    {
        return Action::make('deleteNavigationItemMenuAction')
            ->label(__('general.delete'))
            ->icon('heroicon-s-trash')
            ->color('danger')
            ->size('xs')
            ->extraAttributes([
                'class' => 'hidden',
            ])
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                NavigationMenuItem::destroy($arguments['id']);
            });
    }

    protected function getNavigationMenuForm()
    {
        return function (array $arguments) {
            $id = $arguments['id'] ?? null;

            return [
                TextInput::make('name')
                    ->label(__('general.name'))
                    ->required()
                    ->reactive()
                    ->debounce()
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        if (! $state) {
                            return;
                        }

                        $set('handle', Str::slug($state));
                    }),
                TextInput::make('handle')
                    ->label('Handle')
                    ->rules([
                        fn (): Closure => function (string $attribute, $value, Closure $fail) use ($id) {
                            if (NavigationMenu::query()->where('handle', $value)->where('id', '!=', $id)->exists()) {
                                $fail(__('validation.unique', ['attribute' => $value]));
                            }
                        },
                    ])
                    ->required(),
            ];
        };
    }

    protected function getNavigationMenuItemForm()
    {
        return function (array $arguments) {
            return [
                MultilanguageComponent::make([
                    TextInput::make('meta.label')
                        ->label(__('general.label'))
                        ->required(),
                ]),
                
                Select::make('type')
                    ->label(__('general.type'))
                    ->options(NavigationMenuItem::getTypeOptions())
                    ->required()
                    ->reactive(),
                Group::make()
                    ->visible(function (Get $get) {
                        if (! $get('type')) {
                            return false;
                        }

                        $type = NavigationMenuItem::getType($get('type'));

                        return $type ? ! empty($type::getAdditionalForm()) : false;
                    })
                    ->schema(function (Get $get) {
                        if (! $get('type')) {
                            return [];
                        }

                        $type = NavigationMenuItem::getType($get('type'));

                        return $type ? $type::getAdditionalForm() : [];
                    }),
            ];
        };
    }

    public function sortNavigationMenuItems($ids, $parentId = null)
    {
        $startOrder = 1;
        foreach ($ids as $id) {
            NavigationMenuItem::query()
                ->where('id', $id)
                ->update([
                    'order_column' => $startOrder++,
                    'parent_id' => $parentId,
                ]);
        }
    }
}

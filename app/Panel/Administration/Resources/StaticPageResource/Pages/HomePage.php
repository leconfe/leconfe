<?php

namespace App\Panel\Administration\Resources\StaticPageResource\Pages;

use App\Facades\StaticPageBlockFacade;
use App\Models\StaticPage;
use App\Panel\Administration\Resources\StaticPageResource;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class HomePage extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;
    use InteractsWithFormActions;

    public static bool $hasInlineLabels = true;

    protected static ?string $navigationGroup = 'Pages';

    protected static string $resource = StaticPageResource::class;

    protected static string $view = 'panel.administration.resources.static-page-resource.pages.home-page';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return "Home";
    }

    public function mount()
    {
        $this->record = StaticPage::getHome();

        $this->authorizeAccess();

        $this->form->fill([
            ...$this->record->attributesToArray(),
            'contents' => $this->record->getMeta('contents'),
        ]);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function form(Form $form): Form
    {
        return $form
            ->extraAttributes(['class' => 'max-w-3xl'])
            ->columns(1)
            ->schema([
                StaticPageBlockFacade::getBuilder(),
            ])
            ->operation('edit')
            ->model($this->getRecord())
            ->statePath('data');
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->authorizeAccess();

        try {
            DB::beginTransaction();

            $data = $this->form->getState();

            $this->handleRecordUpdate($this->getRecord(), $data);

        } catch (Halt $exception) {
            DB::rollBack();

            return;
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        DB::commit();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        $record->setMeta('contents', $data['contents']);

        return $record;
    }

    protected function getSavedNotification(): ?Notification
    {
        $title = __('filament-panels::resources/pages/edit-record.notifications.saved.title');

        if (blank($title)) {
            return null;
        }

        return Notification::make()
            ->success()
            ->title($title);
    }


    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->submit('save')
            ->keyBindings(['mod+s']);
    }
}

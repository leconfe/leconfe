<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\Presentation;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Renderless;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PresentationDetail extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string $view = 'panel.scheduledConference.pages.presentation-detail';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 99;

    public ?array $formData = [];

    public Presentation $record;

    public function mount(): void
    {
        if (! $this->record->is_final) {
            throw new NotFoundHttpException;
        }

        $this->record->load(['submission' => ['authors.role', 'meta']]);
        $this->record->registerView((int) auth()->id());
    }

    #[Renderless]
    public function toggleLike(): void
    {
        $this->record->toggleLike((int) auth()->id());
    }

    public function getTitle(): string | Htmlable
    {
        return $this->record->submission->getMeta('title');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [

        ];
    }

    public static function getRoutePath(): string
    {
        return '/presentations/{record}';
    }
}

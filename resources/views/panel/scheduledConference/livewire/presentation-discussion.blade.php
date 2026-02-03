<section class="bg-white dark:bg-gray-900 antialiased">
	<div class="mx-auto">
		<form class="mb-6 space-y-4" wire:submit="submit">
			{{ $this->form }}
			<x-filament::button type="submit">
				{{ __('general.submit') }}
			</x-filament::button>
		</form>
		<div class="space-y-6">
			@forelse ($record->comments as $comment)
				 @livewire(App\Panel\ScheduledConference\Livewire\PresentationCommentComponent::class, ['record' => $comment], key('comment-' . $comment->getKey()))
			@empty
				<div class="text-gray-600">
					There's nothing here
				</div>
			@endforelse
		</div>
	</div>
</section>
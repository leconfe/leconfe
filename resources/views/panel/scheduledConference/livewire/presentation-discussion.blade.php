<section class="bg-white dark:bg-gray-900 antialiased">
	<div class="mx-auto">
		<form class="space-y-4" wire:submit="submit">
			{{ $this->form }}
			<x-filament::button type="submit">
				{{ __('general.submit') }}
			</x-filament::button>
		</form>
		@if($record->comments->isNotEmpty())
		<div class="divide-y">
			@foreach ($record->comments as $comment)
				 @livewire(App\Panel\ScheduledConference\Livewire\PresentationCommentComponent::class, ['record' => $comment], key('comment-' . $comment->getKey() . Str::random(7)))
			@endforeach
		</div>
		@endif
	</div>
</section>
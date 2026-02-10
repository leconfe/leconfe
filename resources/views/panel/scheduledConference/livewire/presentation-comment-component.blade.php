<div 
	id="comment-{{ $record->getKey() }}"
	@class([
		"py-6" => !$record->parent_id,
		"ml-6 lg:ml-12 py-4" => $record->parent_id,
	])
	>
	<article class="text-base">
		<div class="flex justify-between items-center mb-2">
			<div class="flex items-center">
				<p class="inline-flex items-center mr-3 text-sm text-gray-900 dark:text-white font-semibold">
					<img
						class="mr-2 w-8 h-8 rounded-full"
						src="{{ $record->user->getFilamentAvatarUrl() }}"
						alt="{{ $record->user->full_name }}">
						{{ $record->user->full_name }}
				</p>
				<p class="text-sm text-gray-600 dark:text-gray-400">
					<time pubdate datetime="{{ $record->created_at->format('Y-m-d') }}" title="{{ $record->created_at->format(Setting::get('format_date')) }}">{{ $record->created_at->format(Setting::get('format_date')) }} </time>
				</p>
			</div>
			@if (auth()->user()->can('edit', $record) || auth()->user()->can('delete', $record))	
				<x-filament-actions::group :actions="[
					$this->editAction,
					$this->deleteAction,
				]" />
			@endif
		</div>
		<div class="text-gray-500 dark:text-gray-400 user-content">
			{{-- {{ $record->getMeta('content') }} --}}
			{!! $record->getMeta('content') !!}
		</div>

		@if(!$record->parent_id)
			<div class="flex items-center mt-4 space-x-4">
				{{ $this->replyAction }}
			</div>
			@if($record->childs->isNotEmpty())
				@foreach ($record->childs as $child)
					@livewire(App\Panel\ScheduledConference\Livewire\PresentationCommentComponent::class, ['record' => $child], key('comment-' . $child->getKey()))
				@endforeach
			@endif
		@endif
		<x-filament-actions::modals />
	</article>
</div>

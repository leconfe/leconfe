<div id="comment-{{ $record->getKey() }}">
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
			<x-filament-actions::group :actions="[
				$this->editAction,
				$this->deleteAction,
			]" />
		</div>
		<p class="text-gray-500 dark:text-gray-400">
			{{ $record->getMeta('content') }}
		</p>
		<div>
			
		</div>
		<x-filament-actions::modals />
	</article>
</div>

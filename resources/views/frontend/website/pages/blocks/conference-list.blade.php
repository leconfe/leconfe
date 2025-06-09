<x-website::heading-title :title="$title" class="mb-5" />
<div class="grid sm:grid-cols-2 ">
	@if ($conferences->isNotEmpty())
		<div class="scheduled-conferences">
			<div class="space-y-6">
				@foreach ($conferences as $scheduledConference)
					<div class="scheduled-conference sm:flex gap-4">
						@if ($scheduledConference->hasThumbnail())
							<div class="scheduled-conference-cover max-w-40">
								<img src="{{ $scheduledConference->getThumbnailUrl() }}"
									alt="{{ $scheduledConference->title }}">
							</div>
						@endif
						<div class="information flex-1 space-y-3">
							<div>
								<h3 class="scheduled-conference-title">
									<a href="{{ $scheduledConference->getUrl() }}"
										class="link link-primary link-hover font-medium">{{ $scheduledConference->title }}</a>
								</h3>
								<div class="scheduled-conference-date text-sm text-gray-700">
									@if ($scheduledConference->date_start)
										{{ $scheduledConference->date_start->format(Setting::get('format_date')) }}
									@endif
									@if ($scheduledConference->date_end)
										- {{ $scheduledConference->date_end->format(Setting::get('format_date')) }}
									@endif
								</div>
							</div>
							@if ($scheduledConference->getMeta('summary'))
								<div class="scheduled-conference-summary user-content">
									{!! $scheduledConference->getMeta('summary') !!}
								</div>
							@endif
						</div>
					</div>
				@endforeach
			</div>
		</div>
	@endif
</div>

<div class="speaker-block p-4 space-y-4">
	@if(!empty($description))
	<div class="prose prose-sm max-w-none">
		{!! $description !!}
	</div>
	@endif
	{{-- @dd(get_defined_vars()) --}}
	<div class="speakers space-y-4">
		@foreach ($speakers as $speaker)
			<div>
				<div class="flex items-center gap-2">
					@if(!empty($speaker['profile_picture']))
						<img class="w-24 h-24 rounded-full object-cover" src="{{ url(array_values($speaker['profile_picture'])[0]) }}" alt="Rounded avatar">
					@endif
					<div class="">
						<div class="speaker-name text-lg font-medium">{{ $speaker['name'] }}</div>
						@if($affiliation =  Arr::get($speaker, 'affiliation'))
							<div class="speaker-affiliation text-sm text-gray-600 font-semibold">{{ $affiliation }}</div>
						@endif
						
						@if($biography =  Arr::get($speaker, 'biography'))
							<div class="speaker-biography text-sm text-gray-600">{{ $biography }}</div>
						@endif
						
						<div class="speaker-scholars flex flex-wrap items-center gap-1 mt-2">
							@if($orcidUrl = Arr::get($speaker, 'orcid_url'))
								<a href="{{ $orcidUrl }}" target="_blank">
									<x-academicon-orcid class="orcid-logo" />
								</a>
							@endif
							@if($googleScholarUrl = Arr::get($speaker, 'google_scholar_url'))
								<a href="{{ $googleScholarUrl }}" target="_blank">
									<x-academicon-google-scholar class="google-scholar-logo" />
								</a>
							@endif
							@if($scopusUrl = Arr::get($speaker, 'scopus_url'))
								<a href="{{ $scopusUrl }}" target="_blank">
									<x-academicon-scopus class="scopus-logo" />
								</a>
							@endif
						</div>
					</div>
				</div>
			</div>
		@endforeach
	</div>
</div>
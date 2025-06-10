<div class="logo-section space-y-4">
	@if($title)
		<x-website::heading-title :title="$title" class="mb-5" />
	@endif
    @if (!empty($description))
        <div class="prose prose-sm max-w-none">
            {!! $description !!}
        </div>
    @endif

    <div class="logos flex flex-wrap items-center gap-4">
		@foreach ($logos as $logo)	
			<div class="logo">
				<img class="max-h-32"
					src="{{ url($logo['image']) }}"
					alt="{{ $logo['name'] }}">
			</div>
		@endforeach
    </div>
</div>

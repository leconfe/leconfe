<div class="logo-block p-4 space-y-4">
    @if (!empty($description))
        <div class="prose prose-sm max-w-none">
            {!! $description !!}
        </div>
    @endif

    <div class="logos flex flex-wrap items-center gap-4">
		@foreach ($logos as $logo)	
			<div class="logo">
				<img class="max-h-32"
					src="{{ url(Arr::first($logo['image'])) }}"
					alt="European">
			</div>
		@endforeach
    </div>
</div>

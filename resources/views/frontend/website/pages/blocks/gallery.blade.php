@if($title)
    <x-website::heading-title :title="$title" class="mb-5" />
@endif
<div class="grid grid-cols-2 md:grid-cols-3 gap-4">
    @foreach ($images as $image)
        <a class="h-72 overflow-hidden" target="_blank" href="{{ url($image) }}">
            <img class="w-full h-full object-cover rounded-lg"
                src="{{ url($image) }}" alt="">
        </a>
    @endforeach
</div>

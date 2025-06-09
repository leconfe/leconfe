<div class="p-4 grid grid-cols-2 md:grid-cols-3 gap-4">
    @foreach ($images as $image)
        <div class="h-60 overflow-hidden">
            <img class="w-full h-full object-cover rounded-lg"
                src="{{ url($image) }}" alt="">
        </div>
    @endforeach
</div>

@props([
	'reviews_count' => 0,
	'completed_reviews_count' => 0,
])

<div class="flex items-center gap-1">
	<x-heroicon-o-user class="h-4 w-4"/> 
	<span>
		{{$completed_reviews_count}}/{{$reviews_count}}
	</span>
</div>
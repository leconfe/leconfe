@props([
	'count' => 1, 
])

<div class="flex items-center">
	@for ($i = 0; $i < $count; $i++)
		<x-heroicon-m-star class="h-4 w-4" />
	@endfor
</div>
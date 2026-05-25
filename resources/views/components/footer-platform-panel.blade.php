@once
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link
		href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap"
		rel="stylesheet"
	/>
@endonce

<div class="platform-footer" style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
	<div class="mx-auto p-2 flex">
		<a href="https://leconfe.com" rel="external" target="_blank" class="mx-auto flex items-center text-sm gap-x-2">
			<img src="{{ Vite::asset('resources/assets/images/logo.png') }}" class="h-4" alt="leconfe-logo-footer">
			<span class="font-medium text-gray-500">Leconfe : Academic Conference Platform</span>
		</a>
	</div>
</div>

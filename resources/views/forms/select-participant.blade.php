<div class="flex items-center gap-4 cursor-pointer">
    <div style="height: 50px; width: 50px;">
        <img src="{{ $participant->getFilamentAvatarUrl() }}" style="height: 50px; width: 50px;" class="max-w-none object-cover object-center rounded-full ring-white dark:ring-gray-900">
    </div>
    <div>
        <p>{{ $participant->full_name }}</p>
        <p class="text-sm text-gray-500 dark:text-gray-300">{{ $participant->email }}</p>
    </div>
</div>
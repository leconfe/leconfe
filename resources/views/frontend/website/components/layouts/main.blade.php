@props([
    'sidebars' => \App\Facades\SidebarFacade::get(),
    'showSidebar' => true,
])

@php
    $isShowSidebar = $showSidebar && $sidebars->isNotEmpty();
@endphp

<div @class(['page-main'])>
    <div @class(['page-content', 'lg:col-span-9' => $isShowSidebar, 'lg:col-span-full' => !$isShowSidebar])>
        {{ $slot }}
    </div>

    @if ($isShowSidebar)
        <x-website::layouts.sidebar :sidebars="$sidebars"/>
    @endif
</div>

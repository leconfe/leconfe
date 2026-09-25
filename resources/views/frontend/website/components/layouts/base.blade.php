@props([
    'title' => null,
])
<!DOCTYPE html>
<html 
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('app.rtl_locales'), true) ? 'rtl' : 'ltr' }}"
    >
    <x-website::layouts.head :title="$title" />

    <body class="page antialiased" x-data>
        
        {{ $slot }}
        
        @livewireScriptConfig

        @hook('Frontend::Views::Body::End')
    </body>

</html>

import preset from '../../../vendor/filament/filament/tailwind.config.preset'

import defaultTheme from 'tailwindcss/defaultTheme'

export default {
    presets: [preset],
    theme: {
        extend: {
            fontFamily: {
                sans: [
                    '"Twemoji Country Flags"',
                    'var(--font-family)',
                    ...defaultTheme.fontFamily.sans,
                ],
            },
        },
    },
    content: [
        './app/Administration/**/*.php',
        './app/Filament/**/*.php',
        './app/Livewire/**/*.php',
        './app/Panel/**/*.php',
        './resources/views/components/**/*.blade.php',
        './resources/views/frontend/scheduledConference/pages/**/*.blade.php',
        './resources/views/administration/**/*.blade.php',
        './resources/views/examples/**/*.blade.php',
        './resources/views/filament/**/*.blade.php',
        './resources/views/forms/**/*.blade.php',
        './resources/views/panel/**/*.blade.php',
        './resources/views/vendor/**/*.blade.php',
        './resources/views/infolists/**/*.blade.php',
        './resources/views/forms/**/*.blade.php',
        './resources/views/tables/**/*.blade.php',
        './vendor/awcodes/filament-tiptap-editor/resources/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    plugins: [require('tailwindcss-animate')],
}

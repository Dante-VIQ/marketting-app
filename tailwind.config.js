import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    // Enable manual dark mode via class toggling on the <html> element
    darkMode: 'class',

    // Scan all Laravel Blade, Alpine, Livewire, and JavaScript files for class names
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Customized Slate palette mapping if needed for fine control
                slate: {
                    950: '#020617',
                },
            },
            backdropBlur: {
                sm: '2px',
                xs: '4px',
            },
            boxShadow: {
                '2xs': '0 1px 2px 0 rgb(0 0 0 / 0.03)',
                'xs': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            },
        },
    },

    plugins: [
        forms,
        typography,
    ],
};
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Clean sans-serif for UI and numbers.
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                // Serif headings for the editorial hospitality feel.
                serif: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                // Warm off-white backgrounds.
                sand: {
                    50: '#FAF8F3',
                    100: '#F4F0E7',
                    200: '#E9E2D2',
                    300: '#DBD0B8',
                },
                // Blue Karma petrol-blue — primary brand + text. (#15607F)
                ink: {
                    DEFAULT: '#15607F',
                    50: '#ECF3F7',
                    100: '#D4E4EC',
                    200: '#A6C8D7',
                    300: '#6FA7BF',
                    400: '#3F89A6',
                    500: '#206E8F',
                    600: '#15607F',
                    700: '#124E68',
                    800: '#0E3C50',
                    900: '#0A2B3A',
                },
                // Warm gold accent (single accent colour).
                gold: {
                    DEFAULT: '#C9A24B',
                    50: '#FBF6EA',
                    100: '#F3E7C6',
                    200: '#E6CF8E',
                    300: '#D9B85C',
                    400: '#C9A24B',
                    500: '#A9853A',
                    600: '#86692C',
                },
            },
        },
    },

    plugins: [forms],
};

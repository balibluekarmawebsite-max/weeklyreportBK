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
                // Deep teal / ink — primary brand + text.
                ink: {
                    DEFAULT: '#0F3D3E',
                    50: '#EAF1F1',
                    100: '#CFE0E0',
                    200: '#9CBFC0',
                    300: '#6A9D9E',
                    400: '#3C7A7B',
                    500: '#1E5859',
                    600: '#154748',
                    700: '#0F3D3E',
                    800: '#0A2C2D',
                    900: '#061E1F',
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

import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
// require('@tailwindcss/aspect-ratio'); // <<< REMOVE THIS LINE

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans], // You might want to change this to Vazirmatn later
            },
        },
    },

    plugins: [
        forms,
        require('@tailwindcss/aspect-ratio'), // <<< ADD THIS LINE HERE
    ],
};
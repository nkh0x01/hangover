import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Modules/**/Filament/**/*.php',
        './app/Livewire/**/*.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    red: {
                        50: '#FEF2F4',
                        100: '#FDE6EA',
                        200: '#FBC9D2',
                        300: '#F89BAA',
                        400: '#F26282',
                        500: '#E8112D',
                        600: '#C70E26',
                        700: '#A50C20',
                        800: '#860A1A',
                        900: '#6E0815',
                    },
                    ink: '#1B1B1B',
                    cream: {
                        DEFAULT: '#F5EFE6',
                        50: '#FBF8F3',
                        100: '#F5EFE6',
                        200: '#EAE0CC',
                        300: '#D9C8A6',
                    },
                    gold: {
                        DEFAULT: '#C9A227',
                        50: '#FAF6E8',
                        100: '#F4ECC9',
                        500: '#C9A227',
                        700: '#9B7E1F',
                    },
                },
            },
            fontFamily: {
                sans: ['"Noto Sans Georgian"', 'Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Noto Serif Georgian"', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            borderRadius: {
                '4xl': '2rem',
            },
            boxShadow: {
                card: '0 4px 16px -4px rgba(27, 27, 27, 0.08)',
                'card-lg': '0 12px 32px -8px rgba(27, 27, 27, 0.12)',
            },
        },
    },
    plugins: [],
};

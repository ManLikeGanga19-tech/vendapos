import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // CSS only: the marketing site ships no JavaScript so it stays fast on entry-level phones.
            input: ['resources/css/app.css'],
            refresh: true,
            fonts: [
                // Downloaded at build time and served from our own origin.
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                    preload: [{ weight: 400 }, { weight: 700 }],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

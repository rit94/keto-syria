import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Arabic UI face with matching Latin. Exposed as --font-plex (see resources/css/app.css).
                bunny('IBM Plex Sans Arabic', {
                    alias: 'plex',
                    weights: [400, 500, 600, 700],
                    subsets: ['arabic', 'latin'],
                    preload: [{ weight: 400 }, { weight: 600 }],
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

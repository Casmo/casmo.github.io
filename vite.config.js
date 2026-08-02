import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * laravel-vite-plugin disables Vite's publicDir, so during `npm run dev` the dev
 * server does not serve public/. A root absolute url like url("/assets/tilemap.png")
 * then resolves against the dev server (127.0.0.1:5173) instead of the app and 404s.
 * Rewrite those to ASSET_URL/APP_URL, where Laravel serves public/ from. Relative
 * urls (the fonts in resources/) are rewritten by Vite before this runs and are
 * already absolute, so they keep pointing at the dev server.
 */
function publicAssetUrl(baseUrl) {
    return {
        name: 'public-asset-url',
        apply: 'serve',
        enforce: 'post',
        transform(code, id) {
            if (! /\.(css|s[ac]ss|less|styl)(\?|$)/.test(id)) {
                return null;
            }

            // Plain css when the browser asks for the stylesheet directly, a JS module
            // (with escaped quotes) when it is imported, so allow for the backslash.
            return code.replace(/url\((\s*\\?['"]?)\/(?!\/)/g, `url($1${baseUrl}/`);
        },
    };
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const assetUrl = (env.ASSET_URL || env.APP_URL || '').replace(/\/+$/, '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/site.css', 'resources/js/site.js'],
                refresh: true,
            }),
            tailwindcss(),
            assetUrl && publicAssetUrl(assetUrl),
        ],
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});

import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    server: {
        //....
		headers: {
        	'Access-Control-Allow-Origin': '*',
        },
    },
    plugins: [
        laravel({
            input: [
                "resources/panel/css/panel.css",
                "resources/panel/js/panel.js",
                "resources/frontend/css/frontend.css",
                "resources/frontend/js/frontend.js",
            ],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            "@": "/resources",
        },
    },
});

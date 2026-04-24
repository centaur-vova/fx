import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import esbuild from 'esbuild';
import 'dotenv/config';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const envPath = path.resolve(__dirname, '.env');

const wsUrl = process.env.WS_URL || 'wss://fast.af.l3373.xyz/ws';
const uiTheme = process.env.UI_THEME || 'fast';

// Copy theme specific javascript the theme.js
copyTheme(uiTheme);

esbuild.build({
    entryPoints: ['resources/js/app.js'],
    bundle: true,
    minify: true,
    outfile: `public/dist/app.min.js`,
    define: {
        WS_URL: JSON.stringify(wsUrl)
    }
}).catch(() => process.exit(1));

function copyTheme(themeName) {
    const src = path.join(__dirname, `resources/js/modules/themes/${themeName}.js`);
    const dest = path.join(__dirname, 'resources/js/modules/themes/theme.js');
    fs.copyFileSync(src, dest);
}

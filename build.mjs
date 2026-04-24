import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import esbuild from 'esbuild';
import 'dotenv/config';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const envPath = path.resolve(__dirname, '.env');

const wsUrl = process.env.WS_URL || 'wss://fast.af.l3373.xyz/ws';

esbuild.build({
    entryPoints: ['resources/js/app.js'],
    bundle: true,
    minify: true,
    outfile: `public/dist/app.min.js`,
    define: {
        WS_URL: JSON.stringify(wsUrl)
    }
}).catch(() => process.exit(1));
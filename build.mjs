import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import esbuild from 'esbuild';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const envPath = path.resolve(__dirname, '.env');

// Read WS_URL from .env
let WS_URL = 'wss://fast.af.l3373.xyz/ws';

if (fs.existsSync(envPath)) {
    const content = fs.readFileSync(envPath, 'utf-8');
    const match = content.match(/^WS_URL=(.+)$/m);
    if (match) {
        WS_URL = match[1].trim();
    }
}

esbuild.build({
    entryPoints: ['resources/js/app.js'],
    bundle: true,
    minify: true,
    outfile: 'public/dist/app.min.js',
    define: {
        WS_URL: JSON.stringify(WS_URL)
    }
}).catch(() => process.exit(1));
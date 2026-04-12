import esbuild from 'esbuild';

const WS_PORT = process.env.WS_PORT || '8080';

esbuild.build({
    entryPoints: ['resources/js/app.js'],
    bundle: true,
    minify: true,
    outfile: 'public/dist/app.min.js',
    define: {
        WS_PORT: JSON.stringify(WS_PORT)
    }
}).catch(() => process.exit(1));
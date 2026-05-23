import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { fileURLToPath } from 'url';
import esbuild from 'esbuild';
import yaml from 'js-yaml';
import * as sass from 'sass';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';
import 'dotenv/config';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Generate a random hash to bust browser cache on every build
const buildHash = crypto.randomBytes(4).toString('hex');

// Output filenames with cache-busting hashes
const appJsFile = `app.${buildHash}.js`;
const themesJsFile = `themes.${buildHash}.js`;

// Paths
const themesDir = path.join(__dirname, 'themes');
const distDir = path.join(__dirname, 'public/dist');
const themesDistDir = path.join(distDir, 'themes');
const cssDir = path.join(__dirname, 'resources/css');
const resourcesDir = path.join(__dirname, 'resources');

// Clean previous build artifacts
if (fs.existsSync(distDir)) {
    fs.rmSync(distDir, { recursive: true, force: true });
    console.log('🧹 Cleaned public/dist/');
}

// Ensure output directories exist
if (!fs.existsSync(distDir)) fs.mkdirSync(distDir, { recursive: true });
if (!fs.existsSync(themesDistDir)) fs.mkdirSync(themesDistDir, { recursive: true });

// ======================
// 1. Bundle & minify application JS
// ======================
console.log('🐎 Building app.js...');

await esbuild.build({
    entryPoints: ['resources/js/app.js'],
    bundle: true,
    minify: true,
    outfile: path.join(distDir, appJsFile),
    define: {
    }
});

console.log(`  ✅ ${appJsFile} `);

// ======================
// 2. Build base Tailwind CSS
// ======================
console.log('🎨 Building Tailwind CSS...');

const tailwindSource = '@tailwind base;@tailwind components;@tailwind utilities;';

const tailwindResult = await postcss([
    tailwindcss('./tailwind.config.js'),
    autoprefixer
]).process(tailwindSource, {
    from: path.join(cssDir, 'tailwind.css'),
    to: path.join(distDir, 'tailwind.css')
});

fs.writeFileSync(path.join(distDir, 'tailwind.css'), tailwindResult.css);
console.log('  ✅ tailwind.css');

// ======================
// 3. Build theme CSS + JS configs
// ======================
console.log('\n🎨 Building themes...\n');

// Read all theme directories
const themes = fs.readdirSync(themesDir).filter(item => {
    return fs.statSync(path.join(themesDir, item)).isDirectory();
});

// Store theme info for themes.js generation
const themeInfo = {};

for (const theme of themes) {
    const themePath = path.join(themesDir, theme);
    const yamlPath = path.join(themePath, 'theme.yaml');

    if (!fs.existsSync(yamlPath)) {
        console.warn(`⚠️  theme.yaml not found in ${themePath}, skipping`);
        continue;
    }

    const config = yaml.load(fs.readFileSync(yamlPath, 'utf8'));
    console.log(`📦 Theme: ${config.name} (${theme})`);

    // --- Build theme CSS ---
    const loaderScss = path.join(cssDir, 'loader.scss');
    const varsScss = path.join(themePath, 'variables.scss');
    const themeScss = path.join(themePath, 'theme.scss');
    const baseScss = path.join(cssDir, 'style.scss');

    // --- variables.scss must be present in each theme ---
    validateThemeVars(theme, varsScss);

    // Concatenate SCSS sources: base styles + variables + theme overrides
    let scssContent = '';
    if (fs.existsSync(loaderScss)) scssContent += fs.readFileSync(loaderScss, 'utf8') + '\n\n';
    if (fs.existsSync(varsScss)) scssContent += fs.readFileSync(varsScss, 'utf8') + '\n\n';
    if (fs.existsSync(baseScss)) scssContent += fs.readFileSync(baseScss, 'utf8') + '\n\n';
    if (fs.existsSync(themeScss)) scssContent += fs.readFileSync(themeScss, 'utf8');

    try {
        const cssResult = sass.compileString(scssContent, {
            loadPaths: [cssDir, themePath],
            style: 'compressed'
        });

        // Each theme CSS file gets its own hash for cache busting
        const cssHash = crypto.randomBytes(3).toString('hex');
        const cssFile = `${theme}.${cssHash}.css`;

        fs.writeFileSync(path.join(themesDistDir, cssFile), cssResult.css);
        console.log(`  ✅ ${cssFile} `);
        themeInfo[theme] = { cssFile };
    } catch (error) {
        console.error(`  ❌ SCSS failed for ${theme}: `, error.message);
        continue;
    }

    // --- Build theme JS config ---
    const jsConfig = {
        name: config.name,
        author: config.author || 'Centaur-Vova',
        description: config.description || '',
        settings: config.settings || {},
        cssFile: `/ dist / themes / ${themeInfo[theme].cssFile} `
    };

    const configContent = `window.THEME_CONFIG = ${JSON.stringify(jsConfig)}; `;

    const configHash = crypto.randomBytes(3).toString('hex');
    const configFile = `${theme}.${configHash}.config.js`;

    // Minify the config file with esbuild
    await esbuild.build({
        stdin: {
            contents: configContent,
            loader: 'js'
        },
        minify: true,
        outfile: path.join(themesDistDir, configFile)
    });

    console.log(`  ✅ ${configFile} `);
    themeInfo[theme].configFile = configFile;
}

// ======================
// 4. Generate themes.js (list of available themes with hashed filenames)
// ======================
console.log('\n📋 Generating themes.js...');

const themeNames = Object.keys(themeInfo);

// Build a map of theme -> hashed filenames (for runtime theme switching)
const themeMapJs = [
    `window.THEME_MAP = ${JSON.stringify(themeInfo)}; `,
    `window.THEMES = ${JSON.stringify(themeNames)}; `
].join('');

await esbuild.build({
    stdin: {
        contents: themeMapJs,
        loader: 'js'
    },
    minify: true,
    outfile: path.join(distDir, themesJsFile)
});

console.log(`  ✅ ${themesJsFile} `);

// ======================
// 5. Copy index.html from resources/ → public/dist
// ======================
console.log('\n📄 Processing index.html...');

let html = fs.readFileSync(path.join(resourcesDir, 'index.html'), 'utf8');

// Replace static filenames with hashed versions
html = html.replace('app.min.js', appJsFile);
html = html.replace('themes.js', themesJsFile);

// Replace theme file references in document.write (initial page load)
for (const theme of themeNames) {
    html = html.replace(
        `/ dist / themes / ${theme}.css`,
        `/ dist / themes / ${themeInfo[theme].cssFile} `
    );
    html = html.replace(
        `/ dist / themes / ${theme}.config.js`,
        `/ dist / themes / ${themeInfo[theme].configFile} `
    );
}

fs.writeFileSync(path.join(__dirname, 'public/index.html'), html);
console.log(`  ✅ public / index.html(hash: ${buildHash})`);

function validateThemeVars(theme, varsPath) {
    const requiredVars = [
        '--color-error', '--color-warning', '--color-success', '--color-info',
        '--color-accent', '--color-urgent', '--color-subtle', '--color-bright', '--color-vivid',
        '--bg-primary', '--bg-secondary', '--bg-elevated', '--bg-control', '--bg-control-disabled', '--bg-overlay',
        '--border-default', '--border-subtle', '--border-emphasized', '--border-disabled',
        '--text-primary', '--text-secondary', '--text-muted', '--text-white',
        '--color-slider-track', '--worker-bar-idle', '--grid-dot', '--divider-subtle',
        '--bg-info', '--bg-warning', '--bg-success'
    ];

    if (!fs.existsSync(varsPath)) {
        console.error(`❌ Theme "${theme}" is missing variables.scss`);
        process.exit(1);
    }

    const content = fs.readFileSync(varsPath, 'utf8');
    const missing = requiredVars.filter(v => !content.includes(v));

    if (missing.length > 0) {
        console.error(`❌ Theme "${theme}" is missing required CSS variables: ${missing.join(', ')}`);
        process.exit(1);
    }
}

console.log('\n🎉 Build complete!');

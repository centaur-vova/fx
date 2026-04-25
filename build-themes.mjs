import fs from 'fs';
import path from 'path';
import yaml from 'js-yaml';
import * as sass from 'sass';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const themesDir = path.join(__dirname, 'themes');
const distDir = path.join(__dirname, 'public/dist/themes');
const cssDir = path.join(__dirname, 'resources/css');

// Ensure dist directories exist
if (!fs.existsSync(distDir)) fs.mkdirSync(distDir, { recursive: true });
if (!fs.existsSync(path.join(__dirname, 'public/dist'))) {
    fs.mkdirSync(path.join(__dirname, 'public/dist'), { recursive: true });
}

// 1. Build Tailwind 3 (global)
console.log('🎨 Building Tailwind CSS 3...');

const tailwindSource = `
@tailwind base;
@tailwind components;
@tailwind utilities;
`;

try {
    const tailwindResult = await postcss([
        tailwindcss('./tailwind.config.js'),
        autoprefixer
    ]).process(tailwindSource, {
        from: path.join(cssDir, 'tailwind.css'),
        to: path.join(__dirname, 'public/dist/tailwind.css')
    });

    const tailwindOut = path.join(__dirname, 'public/dist/tailwind.css');
    fs.writeFileSync(tailwindOut, tailwindResult.css);
    console.log(`  ✅ Tailwind CSS → ${tailwindOut}`);
} catch (error) {
    console.error('  ❌ Failed to build Tailwind CSS:', error.message);
    process.exit(1);
}

// 2. Build each theme
const themes = fs.readdirSync(themesDir).filter(item => {
    const fullPath = path.join(themesDir, item);
    return fs.statSync(fullPath).isDirectory();
});

console.log(`\n🎨 Building ${themes.length} theme(s)...\n`);

for (const theme of themes) {
    const themePath = path.join(themesDir, theme);
    const yamlPath = path.join(themePath, 'theme.yaml');

    if (!fs.existsSync(yamlPath)) {
        console.warn(`⚠️  theme.yaml not found in ${themePath}, skipping`);
        continue;
    }

    const config = yaml.load(fs.readFileSync(yamlPath, 'utf8'));
    console.log(`📦 Theme: ${config.name} (${theme})`);

    // Build theme CSS
    const varsScss = path.join(themePath, 'variables.scss');
    const themeScss = path.join(themePath, 'theme.scss');
    // Always include base styles
    const baseScss = path.join(cssDir, 'style.scss');

    let scssContent = '';

    // 1. Variables
    if (fs.existsSync(varsScss)) {
        scssContent += fs.readFileSync(varsScss, 'utf8') + '\n\n';
    }

    // 2. Base styles (always)
    if (fs.existsSync(baseScss)) {
        scssContent += fs.readFileSync(baseScss, 'utf8') + '\n\n';
    }

    // 3. Theme overrides (if exists)
    if (fs.existsSync(themeScss)) {
        scssContent += fs.readFileSync(themeScss, 'utf8');
    }

    try {
        const cssResult = sass.compileString(scssContent, {
            loadPaths: [cssDir, themePath],
            style: 'compressed'
        });

        const cssOut = path.join(distDir, `${theme}.css`);
        fs.writeFileSync(cssOut, cssResult.css);
        console.log(`  ✅ CSS → ${path.relative(process.cwd(), cssOut)}`);
    } catch (error) {
        console.error(`  ❌ Failed to compile SCSS for ${theme}:`, error.message);
        continue;
    }

    // Build JS config
    const jsConfig = {
        name: config.name,
        author: config.author || 'Centaur-Vova',
        description: config.description || '',
        settings: config.settings || {},
        cssFile: `/dist/themes/${theme}.css`
    };

    const jsOut = path.join(distDir, `${theme}.config.js`);
    fs.writeFileSync(jsOut, `// Theme: ${config.name}
window.THEME_CONFIG = ${JSON.stringify(jsConfig, null, 2)};
`);
    console.log(`  ✅ JS  → ${path.relative(process.cwd(), jsOut)}`);
}

console.log('\n🎉 All themes built successfully!');
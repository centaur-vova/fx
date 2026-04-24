import 'dotenv/config';
import fs from 'fs';

const theme = process.env.UI_THEME || 'fast';

// Copy CSS theme
fs.copyFileSync(`resources/css/themes/${theme}.scss`, 'resources/css/themes/theme.scss');

// Copy JS theme
fs.copyFileSync(`resources/js/modules/themes/${theme}.js`, 'resources/js/modules/themes/theme.js');
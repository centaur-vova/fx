import { THEME_DEFAULTS } from './theme-defaults.js';
import { generators, defaultGenerator } from './color-generators.js';

// Colors cache
const runtimeColors = {};

/**
 * Generates a random hex color if nothing is found in config
 */
const getRandomColor = () => `#${Math.floor(Math.random() * 16777215).toString(16).padStart(6, '0')}`;

/**
 * Calculates brightness of a hex color to determine the best contrast
 * Returns #000000 for light backgrounds and #ffffff for dark ones
 */
const getContrastColor = (hexColor) => {
    // Remove hash if present
    const hex = hexColor.replace('#', '');

    // Convert to RGB
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);

    // Standard YIQ formula for perceived brightness
    const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
    return (yiq >= 128) ? '#000000' : '#ffffff';
};

/**
 * Get the color generator function based on theme config
 */
const getGenerator = () => {
    const generatorName = window.THEME_CONFIG?.settings?.ui?.color_generator;
    return generators[generatorName] || defaultGenerator;
};

/**
 * Resolves the background color for a specific MC
 */
export const getThemeColor = (mc) => {
    // 1. Priority: YAML config
    const themeColor = window.THEME_CONFIG?.settings?.ui?.task_colors?.[mc];
    if (themeColor) return themeColor;

    // 2. Secondary: Runtime cache for generated colors
    if (runtimeColors[mc]) return runtimeColors[mc];

    // 3. Last resort: Generate using theme's color generator
    const generator = getGenerator();
    const newColor = generator(mc);
    runtimeColors[mc] = newColor;
    return newColor;
};

/**
 * Resolves the label text color based on config or auto-contrast
 * Uses optional chaining and nullish coalescing to prevent TypeErrors
 */
export const getLabelTextColor = (mc) => {
    // 1. Safe access to the config value
    const configTextColor = window.THEME_CONFIG?.settings?.ui?.label_text_color;

    // 2. If config exists and not empty, use it
    if (configTextColor) {
        return configTextColor;
    }

    // 3. Otherwise, get the background color and calculate contrast
    // getThemeColor already has its own fallbacks, so it's safe
    const bgColor = getThemeColor(mc);

    return getContrastColor(bgColor);
};

/**
 * Resolves a human-readable label for task status
 * Priority: YAML config -> theme-defaults -> raw status string
 */
export const getStatusLabel = (status) => {
    // 1. Try to get from the globally loaded YAML config
    const yamlLabel = window.THEME_CONFIG?.settings?.ui?.status_labels?.[status];
    if (yamlLabel) return yamlLabel;

    // 2. Try to get from our hardcoded theme-defaults
    const defaultLabel = THEME_DEFAULTS.STATUS_LABELS?.[status];
    if (defaultLabel) return defaultLabel;

    // 3. Fallback to the raw status key if nothing else is found
    return status;
};

/**
 * Clean & fast resolver for UI settings
 */
export const getUISetting = (category, key) => {
    // Just lower it all — simple and consistent
    const cat = category.toLowerCase();
    const k = key.toLowerCase();

    // Check YAML, then fallback to THEME_DEFAULTS
    const themeValue = window.THEME_CONFIG?.settings?.ui?.[cat]?.[k];

    return themeValue !== undefined ? themeValue : THEME_DEFAULTS[category]?.[key];
};

/**
 * Reset cache on theme change
 */
export const resetThemeColors = () => {
    Object.keys(runtimeColors).forEach(key => delete runtimeColors[key]);
};

import { THEME_DEFAULTS } from './theme-defaults.js';
import { generators, defaultGenerator } from './color-generators.js';

// Colors cache
const runtimeColors = {};

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
 * Resolves the theme color for a task or a max-concurrency value.
 *
 * When called with a number (mc), it's used for UI previews (slider).
 * When called with a task object, the color is resolved from the task's
 * id (if the theme uses the `*` generator prefix) or from its mc.
 *
 * @param {object|number} taskOrMc - Task instance or mc value
 * @returns {string} CSS color
 */
export const getThemeColor = (taskOrMc) => {
    const isTask = typeof taskOrMc === 'object' && taskOrMc !== null;
    const mc = isTask ? taskOrMc.mc : taskOrMc;
    const id = isTask ? taskOrMc.id : null;

    // 1. Priority: YAML config — only for slider preview (mc as number)
    if (!isTask) {
        const themeColor = window.THEME_CONFIG?.settings?.ui?.task_colors?.[mc];
        if (themeColor) return themeColor;
    }

    // 2. Resolve generator and seed source
    const raw = window.THEME_CONFIG?.settings?.ui?.color_generator || '';
    const useId = raw.startsWith('*');
    const genName = useId ? raw.slice(1) : raw;
    const generator = generators[genName] || defaultGenerator;

    // Seed: id (if present and generator expects it) or mc
    const seed = (useId && id !== null) ? (id % 255) + 1 : mc;

    // 3. Cache by (generator, seed)
    const cacheKey = `${genName}:${seed}`;
    if (runtimeColors[cacheKey]) return runtimeColors[cacheKey];

    // 4. Generate
    const newColor = generator(seed);
    runtimeColors[cacheKey] = newColor;
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

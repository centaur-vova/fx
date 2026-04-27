// import proxies to fetch settings from window.THEME_CONFIG
import { getThemeColor, getLabelTextColor, getStatusLabel, getUISetting } from './theme-config.js';

// Welcome/branding message
export const BRAND_LOGO = `
            █████╗ ███████╗
           ██╔══██╗██╔════╝
           ███████║█████╗
           ██╔══██║██╔══╝
     ██╗   ██║  ██║██║
     ╚═╝   ╚═╝  ╚═╝╚═╝
    Atomic Flow Orchestrator `;

// PROXIES

/**
 * Reactive proxy for task background colors
 */
export const COLORS = new Proxy({}, {
    get: (target, prop) => getThemeColor(prop)
});

/**
 * Reactive proxy for task labels (text inside shapes)
 */
export const LABEL_COLORS = new Proxy({}, {
    get: (target, prop) => getLabelTextColor(prop)
});

/**
 * Proxy object to access status labels as if they were a static object
 */
export const STATUS_LABELS = new Proxy({}, {
    get: (target, prop) => getStatusLabel(prop)
});

/**
 * Proxy for reactive Progress Bar settings
 */
export const PROGRESS_BAR = new Proxy({}, {
    get: (target, prop) => getUISetting('PROGRESS_BAR', prop)
});

/**
 * Proxy for reactive Level Of Detail (LOD) settings
 */
export const LOD = new Proxy({}, {
    get: (target, prop) => getUISetting('LOD', prop)
});

/**
 * Proxy for reactive zone coordinates
 */
export const COORDS = new Proxy({}, {
    get: (target, prop) => getUISetting('ZONE_COORDS', prop)
});

/**
 * Proxy for task remove delays (milliseconds)
 */
export const REMOVE_DELAYS = new Proxy({}, {
    get: (target, prop) => getUISetting('REMOVE_DELAYS', prop)
});

// WebSocket Protocol
export const WS = {
    EVENT: {
        WELCOME: 'welcome',
        STATUS_CHANGED: 'status.changed',
        METRICS_UPDATE: 'metrics.update',
        PONG: 'pong'
    },
    BINARY_TYPE: {
        STATUS_UPDATE: 0x02,
        METRICS: 0x03 // reserved
    },
    PING_INTERVAL_MS: 3000
};

export const TASK = {
    // Binary id mappings
    STATUS_MAP: {
        0: 'queued',
        1: 'processing',
        2: 'check_lock',
        3: 'progress',
        4: 'completed',
        5: 'lock_acquired',
        6: 'lock_failed',
        7: 'retries_failed',
        8: 'retry'
    },
    LOG_THRESHOLD: 200
};

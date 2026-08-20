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

export const HEALTH_CHECK = {
    INTERVAL_MS: 1000,
};

export const TASK = {
    // Binary id mappings
    STATUS_MAP: {
        0: 'check_lock',
        1: 'progress',
        2: 'completed',
        3: 'lock_acquired',
        4: 'lock_failed',
        5: 'retries_failed',
        6: 'retry'
    },
};

export const TERMINAL_LOG = {
    DISABLED_THRESHOLD: 200,
    MAX_LOG_ENTRIES: 50,

    STATUS_COLORS: {
        completed: 'var(--color-success)',
        retries_failed: 'var(--color-error)',
        lock_failed: 'var(--color-error)',
        retry: 'var(--color-warning)',
        check_lock: 'var(--color-info)',
        lock_acquired: 'var(--color-accent)',
        progress: 'var(--color-urgent)',
    },
};

export const ROUTES = {
    TASKS_CREATE: '/tasks/create',
    HEALTH: '/health',

    API: {
        INSTANCE_TOGGLE: '/api/instance/toggle',
    },
};

export const WORKER_FLASH = {
    SUCCESS: 'success',
    ERROR: 'error',
    RETRY: 'retry',
    WAIT: 'wait',
    DURATION_MS: 400,
};
export const COLORS = {
    1: '#6366f1', // Indigo
    2: '#3b82f6', // Blue
    3: '#06b6d4', // Cyan
    4: '#10b981', // Emerald
    5: '#84cc16', // Lime
    6: '#eab308', // Yellow
    7: '#f97316', // Orange
    8: '#f43f5e', // Rose
    9: '#ef4444', // Red
    10: '#a855f7'  // Purple
};
export const COORDS = {
    queued: 0.125,
    check_lock: 0.375,
    lock_acquired: 0.625,
    progress: 0.625,
    completed: 0.875,
    retries_failed: 0.875,
    lock_failed: 0.125,
};

// Welcome/branding message
export const BRAND_LOGO = `
    ███████╗ █████╗ ███████╗████████╗     █████╗ ███████╗
    ██╔════╝██╔══██╗██╔════╝╚══██╔══╝    ██╔══██╗██╔════╝
    █████╗  ███████║███████╗   ██║       ███████║█████╗
    ██╔══╝  ██╔══██║╚════██║   ██║       ██╔══██║██╔══╝
    ██║     ██║  ██║███████║   ██║    ██╗██║  ██║██║
    ╚═╝     ╚═╝  ╚═╝╚══════╝   ╚═╝    ╚═╝╚═╝  ╚═╝╚═╝     `;

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
        7: 'retries_failed'
    },
    // Human readable labels
    LABELS: {
        queued: 'In queue',
        processing: 'Started',
        check_lock: 'Checking semaphore',
        progress: 'In progress',
        completed: 'Done',
        lock_acquired: 'Accepted',
        lock_failed: 'Timeout',
        retries_failed: 'Max retries reached'
    },
    LOG_THRESHOLD: 300
};

export const UI = {
    PROGRESS_BAR_MIN_SCALE: 0.8,
    PROGRESS_BAR_HEIGHT: 4,
    PROGRESS_BAR_COLOR: '#ffffff',
    LOD: {
        NORMAL_MAX: 300,      // normal size
        MEDIUM_MAX: 500,      // reduced scale
        SCALE_NORMAL: 1,
        SCALE_MEDIUM: 0.5,
        SCALE_SMALL: 0.3,
        MODE_DOT_THRESHOLD: 500
    }
};
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
    LOG_THRESHOLD: 300
};

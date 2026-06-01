/**
 * Hardcoded UI defaults for all themes
 */
export const THEME_DEFAULTS = {
    STATUS_LABELS: {
        queued: 'In queue',
        check_lock: 'Checking semaphore',
        progress: 'In progress',
        completed: 'Done',
        lock_acquired: 'Accepted',
        lock_failed: 'Timeout',
        retries_failed: 'Max retries reached',
        retry: 'Better luck next time',
    },
    PROGRESS_BAR: {
        min_scale: 0.9,
        height: 4,
    },
    LOD: {
        normal_max: 300,
        medium_max: 500,
        scale_normal: 1,
        scale_medium: 0.5,
    },
    ZONE_COORDS: {
        queued: 0.125,
        retry: 0.125, // same as queued
        check_lock: 0.375,
        lock_acquired: 0.625,
        progress: 0.625,
        completed: 0.875,
        retries_failed: 0.875,
        lock_failed: 0.125,
    },
    /**
     * Task removal delays (milliseconds)
     * completed   - delay before removing successfully finished or failed tasks
     * retry_stall - max time a stuck "retry" task stays visible before being purged
     */
    REMOVE_DELAYS: {
        completed: 1000,
        retry_stall: 10000,
    },
};

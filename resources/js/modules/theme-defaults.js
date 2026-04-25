/**
 * Hardcoded UI defaults for all themes
 */
export const THEME_DEFAULTS = {
    STATUS_LABELS: {
        queued: 'In queue',
        processing: 'Started',
        check_lock: 'Checking semaphore',
        progress: 'In progress',
        completed: 'Done',
        lock_acquired: 'Accepted',
        lock_failed: 'Timeout',
        retries_failed: 'Max retries reached',
    },
    PROGRESS_BAR: {
        min_scale: 0.8,
        height: 4,
    },
    LOD: {
        normal_max: 300,
        medium_max: 500,
        scale_normal: 1,
        scale_medium: 0.5,
        scale_dot: 0.3,
    },
    ZONE_COORDS: {
        queued: 0.125,
        check_lock: 0.375,
        lock_acquired: 0.625,
        progress: 0.625,
        completed: 0.875,
        retries_failed: 0.875,
        lock_failed: 0.125,
    }
};

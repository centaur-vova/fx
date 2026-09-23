import { COORDS, TASK_STATUS } from './config.js';
import { getThemeColor } from './theme-config.js';
import { WaveAnimation } from './wave-animation.js';

export class Task {
    constructor(data) {
        // Core task data
        this.id = data.id ?? null;
        this.mc = data.mc ?? null;
        this.title = data.title ?? null;
        this.sem = data.sem ?? 0;

        // Status and progress
        this.status = data.status ?? null;
        this.progress = data.progress ?? 0;
        this.endTime = data.endTime ?? null;

        // Random visual offset for horizontal spread
        this.jitterX = (Math.random() - 0.5) * 0.22;

        // Random vertical position
        this.y = 0.15 + Math.random() * 0.7;

        // Current and target X positions (based on zone coordinates)
        this.currentX = COORDS.queued + this.jitterX;
        this.targetX = COORDS.queued + this.jitterX;

        // Random phase for sinusoidal animation
        this.pulseOffset = Math.random() * Math.PI * 2;

        // Base Y for wave animation (stored separately to avoid drift)
        this._baseY = this.y;

        // In task-store.js, Task constructor
        this.exitSpeed = 0;

        // Wave animation instance (set later)
        this.wave = new WaveAnimation(this.mc ?? 1);

        // lazy cache
        this._color = null;
    }

    /**
     * Creates a lightweight task for UI preview (slider, etc).
     * Not added to the tasks Map. Visual state is fixed.
     *
     * @param {number} mc - concurrency value
     * @returns {Task}
     */
    static preview(mc) {
        const task = new this({ mc, sem: 1 }); // sem=1 for nicey rounded corners

        task.status = null; // yeah, no status for preview
        task.progress = 100;
        task.currentX = 1;
        task.targetX = 1;

        return task;
    }

    getLabel() {
        if (this.title) {
            return this.title.length > 4 ? this.title.slice(0, 4) : this.title;
        }
        if (this.mc !== undefined && this.mc !== null) {
            return this.mc.toString();
        }
        return '?';
    }

    getColor() {
        if (this._color === null) {
            this._color = getThemeColor(this);
        }
        return this._color || '#ffffff';
    }

    /**
     * Checks whether the task should be removed from the view.
     *
     * A task is considered expired when:
     * - It has finished (completed or failed), OR
     * - It is in retry state (to avoid cluttering the view with queued retries)
     *
     * In both cases, the task is removed after `endTime` has passed.
     *
     * @param {number} now - Current timestamp in milliseconds
     * @returns {boolean} True if the task should be removed
     */
    isExpired(now) {
        const isTransient = this.status === TASK_STATUS.RETRY
            || this.status === TASK_STATUS.LOCK_FAILED;

        return isTransient && now > this.endTime;
    }

    isProgress() {
        return this.status === TASK_STATUS.PROGRESS;
    }

    /**
     * Update task state and target position in one go.
     * @param {string} status - New task status
     * @param {number} progress - Progress percentage (0-100)
     */
    update(status, progress) {
        this.status = status;
        this.progress = progress;

        const x = COORDS[status];
        if (x !== undefined) {
            this.targetX = x + this.jitterX;
            this.targetY = 0.15 + Math.random() * 0.7;
        }

        // Trigger exit animation for terminal states
        if (status === TASK_STATUS.COMPLETED) {
            this.exitSpeed = -0.004;
        } else if (status === TASK_STATUS.RETRIES_FAILED) {
            this.exitSpeed = 0.004;
        }
    }
}

export const tasks = new Map();

export function clearTasks() {
    tasks.clear();
}

/**
 * Clears cached colors on all tasks.
 * Call on theme change so colors are recalculated.
 */
export function resetTaskColors() {
    tasks.forEach(task => {
        task._color = null;
    });
}

export function addTask(id, mc, title, sem) {
    const task = new Task({
        id,
        mc,
        title,
        sem,
        status: TASK_STATUS.QUEUED,
    });

    tasks.set(id, task);
    return task;
}

export function getTask(id) {
    return tasks.get(id);
}

export function purgeTransientTasks() {
    tasks.forEach((task, id) => {
        if (task.status === TASK_STATUS.LOCK_FAILED ||
            task.status === TASK_STATUS.RETRY) {
            tasks.delete(id);
        }
    });
}
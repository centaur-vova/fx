import { COLORS, COORDS, TASK_STATUS } from './config.js';
import { WaveAnimation } from './wave-animation.js';

export class Task {
    constructor(data) {
        // Core task data
        this.id = data.id;
        this.mc = data.mc;
        this.title = data.title;
        this.sem = data.sem;

        // Status and progress
        this.status = data.status ?? 'queued';
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
        if (this.title) {
            // TODO
            return '#aaaaaa';
        }
        return COLORS[this.mc] || '#ffffff';
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
        return this.status === TASK_STATUS.RETRY && now > this.endTime;
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

export function addTask(id, mc, title, sem) {
    const task = new Task({
        id,
        mc,
        title,
        sem,
    });

    tasks.set(id, task);
    return task;
}

export function getTask(id) {
    return tasks.get(id);
}
import { COLORS, TASK_STATUS } from './config.js';

export class Task {
    constructor(data) {
        this.id = data.taskId;
        this.mc = data.mc;
        this.title = data.title;
        this.status = data.status;
        this.progress = data.progress;
        this.sem = data.sem;
        this.mode = data.mode;
        this.worker = data.worker;

        this.pulseOffset = Math.random() * Math.PI * 2;
        this.jitterX = data.jitterX ?? (Math.random() - 0.5) * 0.22;
        this.currentX = data.currentX ?? COORDS.queued + this.jitterX;
        this.targetX = data.targetX ?? COORDS.queued + this.jitterX;
        this.y = data.y ?? 0.15 + Math.random() * 0.7;
        this.endTime = data.endTime ?? null;
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
        const isFinished = this.isFinished() || this.status === TASK_STATUS.RETRY;
        return isFinished && now > this.endTime;
    }

    isFinished() {
        return this.status === TASK_STATUS.COMPLETED || this.status === TASK_STATUS.RETRIES_FAILED;
    }

    isProgress() {
        return this.status === TASK_STATUS.PROGRESS;
    }
}

export const tasks = new Map();

export function clearTasks() {
    tasks.clear();
}

export function addTask(rawData) {
    const task = new Task(rawData);
    tasks.set(task.id, task);
    return task;
}

export function getTask(id) {
    return tasks.get(id);
}
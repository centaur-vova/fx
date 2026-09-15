import { ROUTES } from "../modules/config";
import { clearTasks } from "../modules/task-store";

export const tasks = {
    async flashQueue() {
        const label = document.querySelector('.label-queue');
        if (!label) return;
        label.classList.add('flash');
        setTimeout(() => label.classList.remove('flash'), 200);
    },

    async createTasks(count, forceStress, semaphoreDriver) {
        this.flashQueue();

        try {
            const res = await fetch(ROUTES.TASKS_CREATE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    count,
                    semaphore_driver: semaphoreDriver,
                    max_concurrent: this.mc,
                    task_mode: forceStress ? 'stress' : 'observation'
                }),
            });
            const data = await res.json();

            if (!data.success) {
                this.showToast(data.message, false);
                return;
            }
        } catch (e) {
            this.showToast('Connection error', false);
        }
    },

    async fordBronco() {
        this.flashQueue();

        try {
            const res = await fetch(ROUTES.FORD_BRONCO, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            });
            const data = await res.json();

            if (!data.success) {
                this.showToast(data.message, false);
                return;
            }
        } catch (e) {
            this.showToast('Connection error', false);
        }
    },

    confirmPurge() {
        const modal = document.getElementById('purge-modal');
        modal.classList.remove('hidden');
        const confirmBtn = document.getElementById('confirm-purge');
        const cancelBtn = document.getElementById('cancel-purge');

        const cleanup = () => {
            modal.classList.add('hidden');
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', cleanup);
        };

        const handleConfirm = () => {
            fetch('/tasks/purge', { method: 'POST' })
                .then(async res => {
                    if (res.ok) {
                        clearTasks();
                        this.showToast('Queue purged', true);
                    } else {
                        const data = await res.json();
                        this.showToast(data.error || 'Purge failed', false);
                    }
                })
                .catch(() => this.showToast('Connection error', false));
            cleanup();
        };

        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', cleanup);
    },
};
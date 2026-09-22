import { ROUTES, TASK_BTN_MODE } from "../modules/config";
import { clearTasks } from "../modules/task-store";

export const tasks = {
    async flashQueue() {
        const label = document.querySelector('.label-queue');
        if (!label) return;
        label.classList.add('flash');
        setTimeout(() => label.classList.remove('flash'), 200);
    },

    async _post(url, body = null) {
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined,
            });
            const data = await res.json();
            if (!data.success) {
                this.showToast(data.message, false);
                return null;
            }
            return data;
        } catch (e) {
            this.showToast('Connection error', false);
            return null;
        }
    },

    async createTasks(btn) {
        this.flashQueue();

        if (btn.mode === TASK_BTN_MODE.NITRO) {
            return this.createNitro();
        }
        if (btn.mode === TASK_BTN_MODE.RAND) {
            return this.createRand();
        }

        await this._post(ROUTES.TASKS_CREATE, {
            count: btn.tasks,
            semaphore_driver: btn.semaphore_driver,
            task_mode: btn.stress ? 'stress' : 'observation',
            max_concurrent: this.mc,
        });
    },

    async createRand() {
        await this._post(ROUTES.TASKS_RAND);
    },

    async createNitro() {
        await this._post(ROUTES.TASKS_NITRO);
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
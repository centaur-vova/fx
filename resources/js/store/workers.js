import { WORKER_FLASH } from "../modules/config";

export const workers = {
    workers: [],

    initWorkers(count) {
        this.workers = Array.from({ length: count * 2 }, () => ({ status: '' }));
    },

    flashWorker(id, flashType, sem, persistent = false) {
        const workerNum = this.workers.length / 2;
        const idx = (sem * workerNum) + (id % workerNum);
        if (!this.workers[idx]) return;

        this.workers[idx].status = flashType;

        if (!persistent) {
            setTimeout(() => {
                this.workers[idx].status = '';
            }, WORKER_FLASH.DURATION_MS);
        }
    },

    getWorkerClass(index, sem) {
        const workerNum = this.workers.length / 2;
        const idx = (sem * workerNum) + (index % workerNum);
        const status = this.workers[idx]?.status || '';
        return {
            'flash-success': status === WORKER_FLASH.SUCCESS,
            'flash-error': status === WORKER_FLASH.ERROR,
            'flash-retry': status === WORKER_FLASH.RETRY,
            'flash-wait': status === WORKER_FLASH.WAIT,
        };
    },
};
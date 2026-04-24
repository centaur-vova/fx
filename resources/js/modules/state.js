import { UI } from './config.js';
import { clearTasks } from './taskStore.js';

const getDefaults = () => ({
    latency: '--',
    metrics: {
        memory: '--MB',
        connections: '--',
        cpu: '--%',
        taskNum: '--',
        natsStats: {
            messages: 0,
            bytes: 0,
            consumers: 0,
        },
    },
    system: {
        app_version: '...',
        build_date: '...',
        cpu_cores: '--',
        queue_capacity: '--',
        worker_num: 0,
        stream_created_at: '--',
    }
});

export const state = {
    // Defaults
    ...getDefaults(),

    // General settings
    mc: 2,
    mode: 'normal',
    scale: 1,
    renderEnabled: true,
    isLogPanelDisabled: false,

    // Connection status
    isOnline: false,
    reconnectAttempts: 0,

    // Heatmap workers
    workers: [],

    // Toast data
    toast: {
        show: false,
        success: true,
        content: '',
        timeout: null,
    },

    // Toast
    showToast(message, isSuccess = true, count = null) {
        if (this.toast.timeout) clearTimeout(this.toast.timeout);

        this.toast.success = isSuccess;
        if (isSuccess && count !== null) {
            this.toast.content = `<b class="toast-brand">${count}</b> ${message}`;
        } else {
            this.toast.content = (isSuccess ? '' : '<b class="toast-brand">ERROR:</b> ') + message;
        }
        this.toast.show = true;

        this.toast.timeout = setTimeout(() => {
            this.toast.show = false;
            this.toast.timeout = null;
        }, 2000);
    },

    // Workers
    initWorkers(count) {
        this.workers = Array.from({ length: count }, () => ({ status: '' }));
    },

    // Heatmap
    flashWorker(id, isFailed) {
        const idx = id >= this.workers.length ? id % this.workers.length : id;
        if (!this.workers[idx]) return;

        this.workers[idx].status = isFailed ? 'error' : 'success';
        setTimeout(() => {
            this.workers[idx].status = '';
        }, 400);
    },

    // Reset metrics
    resetMetrics() {
        const defaults = getDefaults();

        this.metrics = defaults.metrics;
        this.system = defaults.system;
        this.latency = defaults.latency;
        this.workers = [];
    },

    // Metrics
    updateMetrics(data) {
        this.metrics.memory = data.memory_mb + 'MB';
        this.metrics.connections = data.connections;
        this.metrics.cpu = data.cpu_usage + '%';
        this.metrics.natsStats = data.nats_stats;
    },

    // Purge queue with confirmation
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
            fetch('/api/tasks/purge', { method: 'POST' })
                .then(async res => {
                    if (res.ok) {
                        clearTasks();
                        this.showToast('Queue purged', true);
                    } else {
                        const data = await res.json();
                        this.showToast(data.error || 'Purge failed', false);
                    }
                })
                .catch(err => {
                    this.showToast('Connection error', false);
                });
            cleanup();
        };

        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', cleanup);
    },

    updateTaskNum(total, logThreshold) {
        this.metrics.taskNum = total;

        // LOD Logic
        const lod = UI.LOD;
        if (total <= lod.NORMAL_MAX) {
            this.scale = lod.SCALE_NORMAL;
            this.mode = 'normal';
        } else if (total <= lod.MEDIUM_MAX) {
            this.scale = lod.SCALE_MEDIUM;
            this.mode = 'normal';
        } else {
            this.scale = lod.SCALE_SMALL;
            this.mode = 'dot';
        }

        this.isLogPanelDisabled = total > logThreshold;
    },

    // API - create tasks
    async createTasks(count) {
        try {
            const res = await fetch("/api/tasks/create", {
                method: "POST",
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ count, max_concurrent: this.mc }),
            });
            const data = await res.json();

            if (!data.success) {
                this.showToast(data.message, false);
                return;
            }

            const checkLockZone = document.querySelector('.zone-queue');
            if (checkLockZone) {
                checkLockZone.classList.add('flash');
                setTimeout(() => checkLockZone.classList.remove('flash'), 200);
            }
        } catch (e) {
            this.showToast('Connection error', false);
        }
    }
};

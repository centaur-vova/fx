// resources/js/state.js

const getDefaults = () => ({
    latency: '--',
    metrics: {
        memory: '--MB',
        connections: '--',
        cpu: '--%',
        taskNum: '--',
    },
    system: {
        app_version: '...',
        cpu_cores: '--',
        queue_capacity: '--',
        worker_num: 0
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
        content: ''
    },

    // Getter for formatted capacity
    get formattedCapacity() {
        const cap = this.system.queue_capacity;

        // If it's not a number (e.g. '--'), just return it
        if (isNaN(cap)) return cap;

        // Otherwise, do the math
        return (cap / 1000).toFixed(0) + 'k';
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
        this.metrics.taskNum = data.task_num;
    },

    // Toast
    showToast(count, success, msg) {
        this.toast.success = success;
        this.toast.content = success
            ? `<b class="toast-brand">${count}</b> tasks injected`
            : `<b class="toast-brand">ERROR:</b> ${msg}`;
        this.toast.show = true;
        setTimeout(() => this.toast.show = false, 2000);
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
            this.showToast(count, res.ok && data.success, data.message);
        } catch (e) {
            this.showToast(0, false, "Connection error");
        }
    }
};

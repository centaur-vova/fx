import { COLORS, LABEL_COLORS, LOD } from './config';
import { clearTasks } from './task-store.js';

const getDefaults = () => ({
    latency: '--',
    metrics: {
        memory: '--MB',
        freeMem: '--MB',
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

const getFlowDefaults = () => ({
    min: 1,
    max: 10,
    semaphore_driver: 'shared', // php semaphore by default
    buttons: [
        { label: '1', tasks: 1, class: 'default', stress: false },
        { label: '10', tasks: 10, class: 'default', stress: false },
        { label: '50', tasks: 50, class: 'default', stress: false },
        { label: '100', tasks: 100, class: 'default', stress: false },
        { label: '500', tasks: 500, class: 'warning', stress: true },
        { label: '1000', tasks: 1000, class: 'accent', stress: true },
    ]
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

    // Static Flow settings (Theme-based)
    flow: getFlowDefaults(),

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

    // Overlay when switching themes
    isSwitching: false,

    /**
     * Sync with Theme YAML on start
     */
    init() {
        const themeFlow = window.THEME_CONFIG?.settings?.flow || {};

        // semaphore driver
        this.flow.semaphore_driver = themeFlow.semaphore_driver || 'shared';

        // mc slider settings
        this.flow.min = themeFlow.min_concurrent || 1;
        this.flow.max = themeFlow.max_concurrent || 10;

        // init current mc
        this.mc = themeFlow.default_concurrent || this.flow.min;

        // Replace buttons only when present in YAML
        if (themeFlow.task_buttons?.length > 0) {
            this.flow.buttons = themeFlow.task_buttons.map(btn => ({
                label: btn.label || (btn.tasks || btn).toString(),
                tasks: btn.tasks || btn,
                stress: !!btn.stress,
                class: btn.class || 'default'
            }));
        }
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
        this.metrics.memory = Math.round(data.memory_mb) + 'MB';
        this.metrics.freeMem = Math.round(data.free_mem) + 'MB';
        this.metrics.connections = data.connections;
        this.metrics.cpu = Math.round(data.cpu_usage) + '%';
        this.metrics.natsStats = data.nats_stats;
    },

    switchTheme(themeName) {
        if (!window.THEMES?.includes(themeName)) return;

        const info = window.THEME_MAP?.[themeName];
        if (!info) return;

        this.isSwitching = true;

        // Update CSS
        const oldLink = document.querySelector('link[data-theme]');
        const newLink = document.createElement('link');
        newLink.rel = 'stylesheet';
        newLink.href = `/dist/themes/${info.cssFile}`;
        newLink.setAttribute('data-theme', '');
        newLink.onload = () => {
            this.isSwitching = false;
        };
        if (oldLink) oldLink.remove();
        document.head.appendChild(newLink);

        // Remove old config script
        const oldScript = document.querySelector('script[data-theme-config]');
        if (oldScript) oldScript.remove();

        // Load new config
        const newScript = document.createElement('script');
        newScript.src = `/dist/themes/${info.configFile}`;
        newScript.setAttribute('data-theme-config', '');
        newScript.onload = () => this.init();
        newScript.onerror = () => location.reload();
        document.head.appendChild(newScript);

        // Update URL without reload
        const url = new URL(window.location);
        url.searchParams.set('theme', themeName);
        history.pushState(null, '', url);
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

    get mcColor() {
        return COLORS[this.mc];
    },

    get labelColor() {
        return LABEL_COLORS[this.mc];
    },

    updateTaskNum(total, logThreshold) {
        this.metrics.taskNum = total;

        // LOD Logic
        if (total <= LOD.normal_max) {
            this.scale = LOD.scale_normal;
            this.mode = 'normal';
        } else if (total <= LOD.medium_max) {
            this.scale = LOD.scale_medium;
            this.mode = 'normal';
        } else {
            this.scale = LOD.scale_dot;
            this.mode = 'dot';
        }

        this.isLogPanelDisabled = total > logThreshold;
    },

    async flashQueue() {
        const label = document.querySelector('.label-queue');
        if (!label) return;
        label.classList.add('flash');
        setTimeout(() => label.classList.remove('flash'), 200);
    },

    // API - create tasks
    async createTasks(count, forceStress) {
        this.flashQueue();
        try {
            const res = await fetch("/api/tasks/create", {
                method: "POST",
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    count,
                    semaphore_driver: this.flow.semaphore_driver,
                    max_concurrent: this.mc,
                    stress_mode: forceStress
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
    }
};

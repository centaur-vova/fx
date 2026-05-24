import { COLORS, LABEL_COLORS, LOD, TERMINAL_LOG, ROUTES, } from './config';
import { clearTasks } from './task-store.js';
import { resetThemeColors } from './theme-config.js';

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
    max: 100,
    buttons: [
        { label: '1', tasks: 1, class: 'default', stress: false, semaphore_driver: 'shared' },
        { label: '10', tasks: 10, class: 'default', stress: false, semaphore_driver: 'api', },
        { label: '500', tasks: 500, class: 'warning', stress: true, semaphore_driver: 'shared' },
        { label: '1000', tasks: 1000, class: 'accent', stress: true, semaphore_driver: 'api' },
        { label: 'RAND', tasks: 0, class: 'accent', semaphore_driver: 'api', full_width: true },
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

    api: {
        instances: [],
        stats: {
            up: 0, down: 0, totalRequests: 0, totalErrors: 0
        }
    },

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

        // mc slider settings
        this.flow.min = themeFlow.min_concurrent || 1;
        this.flow.max = themeFlow.max_concurrent || 10;

        // init current mc
        this.mc = themeFlow.default_concurrent || this.flow.min;

        // Replace buttons only when present in YAML
        if (themeFlow.task_buttons?.length > 0) {
            this.flow.buttons = themeFlow.task_buttons.map(btn => ({
                label: btn.label || (btn.tasks || btn).toString(),
                tasks: btn.tasks ?? btn,
                stress: !!btn.stress,
                class: btn.class || 'default',
                full_width: btn.full_width || false,
                semaphore_driver: btn.semaphore_driver || 'shared',
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
        this.workers = Array.from({ length: count * 2 }, () => ({ status: '' }));
    },

    // Heatmap
    flashWorker(id, isFailed, sem) {
        const workerNum = this.workers.length / 2;
        const idx = (sem * workerNum) + (id % workerNum);
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
        newScript.onload = () => {
            resetThemeColors();
            this.init();
        }
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

    updateTaskNum(total) {
        this.metrics.taskNum = total;

        // LOD Logic
        if (total <= LOD.normal_max) {
            this.scale = LOD.scale_normal;
            this.mode = 'normal';
        } else if (total <= LOD.medium_max) {
            this.scale = LOD.scale_medium;
            this.mode = 'normal';
        } else {
            this.scale = 0; // hardcoded, only used to compare against other scales. A dot - it's a dot even in Africa
            this.mode = 'dot';
        }

        this.toggleLogPanelDisabled(total);
    },

    toggleLogPanelDisabled(total) {
        this.isLogPanelDisabled = total > TERMINAL_LOG.DISABLED_THRESHOLD;
    },

    async flashQueue() {
        const label = document.querySelector('.label-queue');
        if (!label) return;
        label.classList.add('flash');
        setTimeout(() => label.classList.remove('flash'), 200);
    },

    // API - create tasks
    async createTasks(count, forceStress, semaphoreDriver) {
        this.flashQueue();

        try {
            const res = await fetch(ROUTES.TASKS_CREATE, {
                method: "POST",
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

    // === API HEALTH / METHODS ===
    async fetchBalancerHealth() {
        try {
            const response = await fetch(ROUTES.HEALTH);
            const { data } = await response.json();

            if (data?.status === 'ok') {
                this.api.instances = data.balancer?.instances || [];
                this.api.stats = {
                    up: data.balancer.up || 0,
                    down: data.balancer.down || 0,
                    totalRequests: data.balancer.total_requests || 0,
                    totalErrors: data.balancer.total_errors || 0,
                };
            }
        } catch (e) {
            console.warn('Failed to fetch balancer health:', e);
        }
    },
    startBalancerPoller(intervalMs = 1000) {
        if (this.balancerPoller) clearInterval(this.balancerPoller);

        this.fetchBalancerHealth();
        this.balancerPoller = setInterval(() => {
            if (this.isOnline) {
                this.fetchBalancerHealth();
            }
        }, intervalMs);
    },

    stopBalancerPoller() {
        if (this.balancerPoller) {
            clearInterval(this.balancerPoller);
            this.balancerPoller = null;
        }
    },

    // Sets API Instance on/off
    async toggleApi(inst) {
        try {
            // Mark as half-open (for visual indication)
            inst.cb_state = 'half-open';
            const alive = inst.alive;
            const hash = inst.hash;

            const response = await fetch(ROUTES.API.INSTANCE_TOGGLE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    hash: inst.hash,
                    alive: !alive,
                })
            });
            const data = await response.json();

            if (data.success) {
                setTimeout(() => this.fetchBalancerHealth(), 100);
            } else {
                this.showToast(data.error || ('Failed to ' + (alive ? 'disable' : 'enable') + instance), false);
            }
        } catch (e) {
            this.showToast('Connection error', false);
        }
    },
};

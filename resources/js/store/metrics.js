import { getDefaults } from './defaults.js';
import { LOD, TERMINAL_LOG } from '../modules/config.js';

export const metrics = {
    ...getDefaults(),

    resetMetrics() {
        const defaults = getDefaults();
        this.metrics = defaults.metrics;
        this.system = defaults.system;
        this.latency = defaults.latency;
        this.workers = [];
    },

    updateMetrics(data) {
        this.metrics.memory = Math.round(data.memory_mb) + 'MB';
        this.metrics.freeMem = Math.round(data.free_mem) + 'MB';
        this.metrics.connections = data.connections;
        this.metrics.cpu = Math.round(data.cpu_usage) + '%';
        this.metrics.natsStats = data.nats_stats;
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
            this.scale = 0;
            this.mode = 'dot';
        }

        this.toggleLogPanelDisabled(total);
    },

    toggleLogPanelDisabled(total) {
        this.isLogPanelDisabled = total > TERMINAL_LOG.DISABLED_THRESHOLD;
    },
};
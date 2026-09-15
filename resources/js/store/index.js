import { metrics } from './metrics.js';
import { semaphore } from './semaphore.js';
import { workers } from './workers.js';
import { api } from './api.js';
import { flow } from './flow.js';
import { toast } from './toast.js';
import { theme } from './theme.js';
import { tasks } from './tasks.js';
import { wsMetrics } from './ws-metrics.js';

export const state = {
    // Global flags
    isOnline: false,
    reconnectAttempts: 0,
    renderEnabled: true,
    isLogPanelDisabled: false,

    // Spread modules
    ...metrics,
    ...semaphore,
    ...workers,
    ...api,
    ...flow,
    ...toast,
    ...theme,
    ...tasks,
    ...wsMetrics,

    // Init
    init() {
        this.initFlow();
    },
};
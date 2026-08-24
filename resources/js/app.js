import Alpine from 'alpinejs';
import { state } from './modules/state';
import { decodeMessage } from './modules/decoder.js';
import { drawShape } from './modules/ui.js';
import { tasks, Task, addTask, clearTasks } from './modules/task-store.js';
import { updateMessagesChart } from './modules/chart.js';
import { setupCanvas } from './modules/canvas.js';
import terminalLog from './modules/terminal-log.js';
import {
    WS,
    HEALTH_CHECK,
    TASK_STATUS,
    BRAND_LOGO,
    PING_INTERVAL_MS,
    STATUS_LABELS,
    COORDS,
    REMOVE_DELAYS,
    TERMINAL_LOG,
    WORKER_FLASH,
} from './modules/config';
import {
    UI,
} from './modules/theme-config.js';

// Init store
window.Alpine = Alpine;
Alpine.store('app', state);
Alpine.start();

// Brand logo
console.log(`%c${BRAND_LOGO}`, "color: #10b981; font-weight: bold;");
console.log("%c» FAST.AF — FAST ATOMIC FLOW", "color: #10b981; font-weight: bold;");
console.log("%c» KERNEL: SWOOLE_6.0_STABLE // MODE: SHARED_ATOMIC", "color: #6b7280;");

const store = Alpine.store('app');

// Canvas setup
const pipelineContainer = document.getElementById("pipeline");
const canvas = document.createElement('canvas');
const ctx = canvas.getContext('2d', { alpha: true });
pipelineContainer.appendChild(canvas);
const ro = setupCanvas(pipelineContainer, store, ctx); // auto-resizer

// Websockets
const connect = () => {
    const wsPort = location.hostname === 'localhost' ? ':8080' : '';
    const wsUrl = `${location.protocol === 'https:' ? 'wss:' : 'ws:'}//${location.hostname}${wsPort}/ws`;

    const ws = new WebSocket(wsUrl);

    ws.binaryType = 'arraybuffer';

    ws.onopen = () => {
        console.log("%c REACTOR ONLINE ", "background: #064e3b; color: #10b981; font-weight: bold;");
        store.isOnline = true;
        store.reconnectAttempts = 0;

        startPinger(ws);
        store.startBalancerPoller(HEALTH_CHECK.INTERVAL_MS);
    };

    ws.onclose = () => {
        store.isOnline = false;
        console.log("%c REACTOR OFFLINE ", "background: #450a0a; color: #f87171; font-weight: bold;");

        store.resetMetrics();
        stopPinger();
        store.stopBalancerPoller();
        clearTasks();

        setTimeout(connect, 3000);
    };

    ws.onmessage = (e) => {
        const msg = decodeMessage(e.data, STATUS_LABELS);
        if (!msg) return;

        const { event, data } = msg;

        switch (event) {
            case WS.EVENT.WELCOME:
                store.system = data;
                store.initWorkers(data.worker_num);
                break;

            case WS.EVENT.STATUS_CHANGED:
                handleUpdateTasks(data);
                break;

            case WS.EVENT.METRICS_UPDATE:
                handleMetrics(data);
                break;

            case WS.EVENT.PONG:
                store.latency = Math.round(performance.now() - data.ts);
                break;
        }
    };

    ws.onerror = (err) => {
        console.error('WebSocket error:', err);
    };
};

const handleUpdateTasks = (data) => {
    // Terminal log
    if (tasks.size < TERMINAL_LOG.DISABLED_THRESHOLD) {
        terminalLog.add(data, tasks);
    }

    const { id, worker, mc, status, sem, message, progress = null } = data;

    let task;

    if (tasks.has(id)) {
        task = tasks.get(id);
    } else {
        task = addTask(id, mc, data.title, sem);
    }

    task.progress = progress;
    task.status = status;
    if (COORDS[status]) task.targetX = COORDS[status] + task.jitterX;

    // Update heatmap
    switch (status) {
        case TASK_STATUS.COMPLETED:
            task.endTime = Date.now() + REMOVE_DELAYS.completed;
            store.flashWorker(worker, WORKER_FLASH.SUCCESS, sem);
            break;
        case TASK_STATUS.RETRIES_FAILED:
            task.endTime = Date.now() + REMOVE_DELAYS.completed;
            store.flashWorker(worker, WORKER_FLASH.ERROR, sem);
            break;
        case TASK_STATUS.RETRY:
            task.endTime = Date.now() + REMOVE_DELAYS.retry_stall;
            break;
        case TASK_STATUS.PROGRESS:
        case TASK_STATUS.CHECK_LOCK:
            store.flashWorker(worker, WORKER_FLASH.WAIT, sem, true);
            break;
        case TASK_STATUS.LOCK_FAILED:
            store.flashWorker(worker, WORKER_FLASH.RETRY, sem);
            break;
    }
};

/**
 * Handles real-time system metrics from WebSocket.
 *
 * @param {Object} data - Metrics payload
 * @param {number} data.connections - Number of active WebSocket clients
 * @param {number} data.memory_mb - Memory usage in MB
 * @param {number} data.free_mem - Free system memory in MB
 * @param {number} data.cpu_usage - CPU usage percentage
 * @param {Object} data.nats_stats - NATS JetStream statistics
 * @param {number} data.nats_stats.messages - Total messages in stream
 * @param {number} data.nats_stats.bytes - Total bytes in stream
 * @param {number} data.nats_stats.consumers - Number of active consumers
 *
 * @example
 * // Example metrics payload:
 * {
 *   "connections": 1,
 *   "memory_mb": 12.83,
 *   "free_mem": 19984.82,
 *   "cpu_usage": 69.33,
 *   "nats_stats": {
 *     "messages": 0,
 *     "bytes": 0,
 *     "consumers": 1
 *   }
 * }
 */
const handleMetrics = (data) => {
    // Update metrics
    store.updateMetrics(data);

    // Update chart(s)
    updateMessagesChart(data.nats_stats.messages ?? 0);
};

// Rendering
const render = () => {
    requestAnimationFrame(render);

    if (store.renderEnabled) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    const now = Date.now();
    tasks.forEach((task, id) => {
        task.currentX += (task.targetX - task.currentX) * 0.1;
        if (task.isExpired(now)) {
            tasks.delete(id);
            return;
        }
        if (store.renderEnabled) {
            drawShape(ctx, task.currentX * store.width, task.y * store.height, 24, task, store.mode, store.scale);
        }
    });

    store.updateTaskNum(tasks.size);
};

// Ping timer
let pingTimer = null;
const startPinger = (ws) => {
    pingTimer = setInterval(() => {
        if (ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ event: 'ping', data: { ts: performance.now() } }));
        }
    }, WS.PING_INTERVAL_MS);
};
const stopPinger = () => { clearInterval(pingTimer); };

// Go!
connect();
requestAnimationFrame(render);

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
    TASK,
    BRAND_LOGO,
    PING_INTERVAL_MS,
    STATUS_LABELS,
    COORDS,
    REMOVE_DELAYS,
    TERMINAL_LOG,
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
};

const handleUpdateTasks = (data) => {
    // Terminal log
    if (tasks.size < TERMINAL_LOG.DISABLED_THRESHOLD) {
        terminalLog.add(data, tasks);
    }

    const { taskId, worker, mc, status, sem, message, progress = null } = data;

    let task;

    if (tasks.has(taskId)) {
        task = tasks.get(taskId);
    } else {
        const jitterX = (Math.random() - 0.5) * 0.22;

        task = new Task({
            taskId,
            mc: mc || store.mc,
            title: data.title,
            y: 0.15 + Math.random() * 0.7,
            jitterX: jitterX,
            currentX: COORDS.queued + jitterX,
            targetX: COORDS.queued + jitterX,
            status: 'queued',
            sem: sem,
            pulseOffset: Math.random() * Math.PI * 2 // random phase
        });

        tasks.set(taskId, task);
    }

    task.progress = progress;
    task.status = status;
    if (COORDS[status]) task.targetX = COORDS[status] + task.jitterX;

    if (status === 'completed' || status === 'retries_failed') {
        task.endTime = Date.now() + REMOVE_DELAYS.completed;
        store.flashWorker(worker, status === 'retries_failed', sem);
    } else if (status === 'retry') {
        task.endTime = Date.now() + REMOVE_DELAYS.retry_stall;
    }
};

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
        if ((task.status === 'completed' || task.status === 'retries_failed' || task.status === 'retry') && now > task.endTime) {
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

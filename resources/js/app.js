import Alpine from 'alpinejs';
import { state } from './modules/state';
import { decodeMessage } from './modules/decoder.js';
import { drawShape } from './modules/ui.js';
import { tasks, clearTasks } from './modules/task-store.js';
import { updateMessagesChart } from './modules/chart.js';
import {
    WS,
    TASK,
    BRAND_LOGO,
    PING_INTERVAL_MS,
    STATUS_LABELS,
    COORDS,
    REMOVE_DELAYS,
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

const resize = () => {
    const w = pipelineContainer.clientWidth || window.innerWidth;
    const h = pipelineContainer.clientHeight || 500;

    store.width = w;
    store.height = h;

    canvas.width = w * window.devicePixelRatio;
    canvas.height = h * window.devicePixelRatio;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';

    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

    // console.log(`Canvas resized: ${w}x${h}`);
}; window.addEventListener('resize', resize);

// Apply resize observer
const ro = new ResizeObserver(() => {
    resize();
});
ro.observe(pipelineContainer);
// And call resize() just to make sure
resize();

// Websockets
const connect = () => {
    const wsUrl = WS_URL;
    const ws = new WebSocket(wsUrl);

    ws.binaryType = 'arraybuffer';

    ws.onopen = () => {
        console.log("%c REACTOR ONLINE ", "background: #064e3b; color: #10b981; font-weight: bold;");
        store.isOnline = true;
        store.reconnectAttempts = 0;
        startPinger(ws);
    };

    ws.onclose = () => {
        store.isOnline = false;
        console.log("%c REACTOR OFFLINE ", "background: #450a0a; color: #f87171; font-weight: bold;");

        store.resetMetrics();
        stopPinger();
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
    const { taskId, worker, mc, status, sem, message, progress = null } = data;

    // Logging
    if (tasks.size < TASK.LOG_THRESHOLD) addLog(taskId, mc, status, message, sem, progress);

    if (!tasks.has(taskId)) {
        const jitterX = (Math.random() - 0.5) * 0.22;
        tasks.set(taskId, {
            mc: mc || store.mc,
            y: 0.15 + Math.random() * 0.7,
            jitterX: jitterX,
            currentX: COORDS.queued + jitterX,
            targetX: COORDS.queued + jitterX,
            status: 'queued',
            sem: sem,
            pulseOffset: Math.random() * Math.PI * 2 // random phase
        });
    }

    const task = tasks.get(taskId);
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

    store.updateTaskNum(tasks.size, TASK.LOG_THRESHOLD);
};

// Helpers
const addLog = (taskId, mc, status, msg, sem, progress = null) => {
    // console.li
    const logContainer = document.getElementById("log-panel");
    if (!logContainer || store.isLogPanelDisabled) return;

    const progressText = progress > 0 ? ` (${progress}%)` : '';
    const entry = document.createElement('div');
    entry.className = 'whitespace-nowrap truncate text-[9px] leading-tight mb-0.5 opacity-80';
    const time = new Date().toLocaleTimeString([], { hour12: false });

    // Status color mapping based on semantic roles
    let statusColor = 'var(--text-muted)';  // default

    /**
     * TODO: needs refactoring, some statuses listed below are not even used lol.
     */
    switch (status) {
        case 'error':
        case 'failed':
            statusColor = 'var(--color-error)';
            break;
        case 'warning':
        case 'retry':
            statusColor = 'var(--color-warning)';
            break;
        case 'success':
        case 'done':
        case 'completed':
            statusColor = 'var(--color-success)';
            break;
        case 'info':
        case 'queue':
            statusColor = 'var(--color-info)';
            break;
        case 'accent':
            statusColor = 'var(--color-accent)';
            break;
        case 'urgent':
            statusColor = 'var(--color-urgent)';
            break;
        default:
            statusColor = 'var(--text-muted)';
    }

    // 0 - shared (php), 1 - api (go)
    const semLabel = sem === 1 ? 'API' : 'PHP';
    const paddedStatus = status.toUpperCase().padEnd(14, ' ');
    // Show taskId in hex
    const hexId = (taskId & 0xFFFF).toString(16).toUpperCase().padStart(4, '0');

    entry.innerHTML = `
        <span style="color: var(--text-secondary)">${time}</span>
        <span style="color: ${statusColor}; font-weight: bold; white-space: pre">${semLabel} » ${paddedStatus}</span>
        <span style="color: var(--text-primary)">${hexId}</span>
        <span style="color: var(--text-muted)">${msg}${progressText}</span>
    `;

    logContainer.appendChild(entry);
    if (logContainer.children.length > 30) logContainer.removeChild(logContainer.firstChild);
    logContainer.scrollTop = logContainer.scrollHeight;
}

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

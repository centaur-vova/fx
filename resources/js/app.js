import Alpine from 'alpinejs';
import { state } from './modules/state';
import { BRAND_LOGO, COLORS, COORDS, TASKS_LOG_THRESHOLD, PING_INTERVAL_MS } from './modules/config';

// Init store
window.Alpine = Alpine;
Alpine.store('app', state);
Alpine.start();

// Brand logo
console.log(`%c${BRAND_LOGO}`, "color: #10b981; font-weight: bold;");
console.log("%c» FAST.AF — FAST ATOMIC FLOW", "color: #10b981; font-weight: bold;");
console.log("%c» KERNEL: SWOOLE_6.0_STABLE // MODE: SHARED_ATOMIC", "color: #6b7280;");

// Keep tasks outside the store for faster access
const tasks = new Map();

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

    console.log(`Canvas resized: ${w}x${h}`);
}; window.addEventListener('resize', resize);

// Apply resize observer
const ro = new ResizeObserver(() => {
    resize();
});
ro.observe(pipelineContainer);
// And call resize() just to make sure
resize();

// Canvas engine
const drawShape = (x, y, size, mc, status) => {
    const s = size * store.scale;
    ctx.fillStyle = COLORS[mc] || '#ffffff';
    const isFinished = status === 'completed' || status === 'retries_failed';
    ctx.globalAlpha = isFinished ? 0.3 : 1;

    if (store.mode === 'dot') {
        ctx.beginPath();
        ctx.arc(x, y, 2 * store.scale, 0, Math.PI * 2);
        ctx.fill();
        return;
    }

    ctx.beginPath();
    ctx.roundRect(x - s / 2, y - s / 2, s, s, 4 * store.scale);
    ctx.fill();

    if (store.scale > 0.8) {
        ctx.fillStyle = 'white';
        ctx.font = `bold ${10 * store.scale}px Inter, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(mc, x, y);
    }
};

// Websockets
const connect = () => {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.host}/ws`;
    const ws = new WebSocket(wsUrl);

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
        tasks.clear();
        setTimeout(connect, 3000);
    };

    ws.onmessage = (e) => {
        try {
            const { event, data } = JSON.parse(e.data);
            switch (event) {
                case 'welcome': store.system = data; store.initWorkers(data.worker_num); break;
                case 'status.changed': handleUpdateTasks(data); break;
                case 'metrics.update': handleMetrics(data); break;
                case 'pong': store.latency = Math.round(performance.now() - data.ts); break;
            }
        } catch (err) { console.error("WS Error", err); }
    };
};

const handleUpdateTasks = (data) => {
    const { taskId, worker, mc, status, message } = data;

    // Logging
    if (tasks.size < TASKS_LOG_THRESHOLD) addLog(taskId, mc, status, message);

    if (!tasks.has(taskId)) {
        const jitterX = (Math.random() - 0.5) * 0.22;
        tasks.set(taskId, {
            mc: mc || store.mc,
            y: 0.15 + Math.random() * 0.7,
            jitterX: jitterX,
            currentX: COORDS.queued + jitterX,
            targetX: COORDS.queued + jitterX,
            status: 'queued'
        });
    }

    const task = tasks.get(taskId);
    task.status = status;
    if (COORDS[status]) task.targetX = COORDS[status] + task.jitterX;

    if (status === 'completed' || status === 'retries_failed') {
        task.endTime = Date.now();
        store.flashWorker(worker, status === 'retries_failed');
    }
};

const handleMetrics = (data) => {
    store.updateMetrics(data);
    // LOD Logic
    const total = parseInt(data.task_num, 10);
    if (total <= 50) { store.scale = 1; store.mode = 'normal'; }
    else if (total <= 500) { store.scale = 0.5; store.mode = 'normal'; }
    else { store.scale = 0.3; store.mode = 'dot'; }

    store.isLogPanelDisabled = tasks.size > TASKS_LOG_THRESHOLD;
};

// Rendering
const render = () => {
    requestAnimationFrame(render);
    if (!store.renderEnabled) return;

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const now = Date.now();

    tasks.forEach((task, id) => {
        task.currentX += (task.targetX - task.currentX) * 0.1;
        if ((task.status === 'completed' || task.status === 'retries_failed') && now - task.endTime > 5000) {
            tasks.delete(id);
            return;
        }
        drawShape(task.currentX * store.width, task.y * store.height, 24, task.mc, task.status);
    });
};

// Helpers
function addLog(taskId, mc, status, msg) {
    const logContainer = document.getElementById("log-panel");
    if (!logContainer || store.isLogPanelDisabled) return;

    const entry = document.createElement('div');
    entry.className = 'whitespace-nowrap truncate text-[9px] leading-tight mb-0.5 opacity-80';
    const time = new Date().toLocaleTimeString([], { hour12: false });
    entry.innerHTML = `<span class="text-gray-600">${time}</span> <span class="text-blue-400 font-bold">[${status.toUpperCase()}]</span> <span class="text-white">${taskId.substring(0, 8)}</span> <span class="text-gray-400">${msg}</span>`;

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
    }, PING_INTERVAL_MS);
};
const stopPinger = () => { clearInterval(pingTimer); };

// Go!
connect();
requestAnimationFrame(render);

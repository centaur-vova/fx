document.addEventListener("DOMContentLoaded", () => {
    const state = {
        tasks: new Map(),
        mc: 2,
        ws: null,
        scale: 1,
        mode: 'normal',
        width: 0,
        height: 0,
        toastTimeout: null,
        reconnectAttempts: 0,
        pingTimer: null,
        isLogPanelDisabled: false,

        // {"worker_num":12,"cpu_cores":12,"queue_capacity":10000, ...}
        system: null,
    };

    const PING_INTERVAL_MS = 3000;
    const TASKS_LOG_THRESHOLD = 300;

    // Configuration from your CSS
    const COLORS = {
        1: '#6366f1', 2: '#10b981', 3: '#f59e0b', 4: '#ea580c', 5: '#f43f5e',
        6: '#8b5cf6', 7: '#d946ef', 8: '#06b6d4', 9: '#84cc16', 10: '#3b82f6'
    };

    const COORDS = {
        queued: 0.125,
        check_lock: 0.375,
        lock_acquired: 0.625,
        progress: 0.625,
        completed: 0.875,
        retries_failed: 0.875, // same as completed
        lock_failed: 0.125,
    };

    const DOM = {
        container: document.getElementById("pipeline"),
        log: document.getElementById("log-panel"),
        mcDisplay: document.getElementById("max-concurrent-display"),
        mcSlider: document.getElementById("max-concurrent-slider"),
    };

    // Welcome/branding message
    const brand = `
    ███████╗ █████╗ ███████╗████████╗     █████╗ ███████╗
    ██╔════╝██╔══██╗██╔════╝╚══██╔══╝    ██╔══██╗██╔════╝
    █████╗  ███████║███████╗   ██║       ███████║█████╗
    ██╔══╝  ██╔══██║╚════██║   ██║       ██╔══██║██╔══╝
    ██║     ██║  ██║███████║   ██║    ██╗██║  ██║██║
    ╚═╝     ╚═╝  ╚═╝╚══════╝   ╚═╝    ╚═╝╚═╝  ╚═╝╚═╝     `;

    console.log(`%c${brand}`, "color: #10b981; font-weight: bold;");
    console.log("%c» FAST.AF — FAST ATOMIC FLOW", "color: #10b981; font-weight: bold;");
    console.log("%c» KERNEL: SWOOLE_6.0_STABLE // MODE: SHARED_ATOMIC", "color: #6b7280;");

    // Setup Canvas
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { alpha: true });
    DOM.container.appendChild(canvas);

    const resize = () => {
        state.width = DOM.container.clientWidth;
        state.height = DOM.container.clientHeight;
        canvas.width = state.width * window.devicePixelRatio;
        canvas.height = state.height * window.devicePixelRatio;
        canvas.style.width = state.width + 'px';
        canvas.style.height = state.height + 'px';
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
    };
    window.addEventListener('resize', resize);
    resize();

    // Drawing Primitives (Your CSS Shapes)
    const drawShape = (x, y, size, mc, status) => {
        ctx.fillStyle = COLORS[mc] || '#ffffff';
        ctx.globalAlpha = isTaskFinished(status) ? 0.3 : 1;

        if (state.mode === 'dot') {
            ctx.beginPath();
            ctx.arc(x, y, 2 * state.scale, 0, Math.PI * 2);
            ctx.shadowBlur = state.mode === 'dot' ? 5 : 0;
            ctx.shadowColor = ctx.fillStyle;
            ctx.fill();
            return;
        }

        const s = size * state.scale;
        ctx.beginPath();

        switch (parseInt(mc, 10)) {
            case 1: // Circle
                ctx.arc(x, y, s / 2, 0, Math.PI * 2); break;
            case 5: // Diamond
                ctx.moveTo(x, y - s / 2); ctx.lineTo(x + s / 2, y); ctx.lineTo(x, y + s / 2); ctx.lineTo(x - s / 2, y); break;
            case 8: // Hexagon
                for (let i = 0; i < 6; i++) {
                    const angle = (Math.PI / 3) * i;
                    ctx.lineTo(x + s / 2 * Math.cos(angle), y + s / 2 * Math.sin(angle));
                } break;
            case 10: // Octagon
                for (let i = 0; i < 8; i++) {
                    const angle = (Math.PI / 4) * i;
                    ctx.lineTo(x + s / 2 * Math.cos(angle), y + s / 2 * Math.sin(angle));
                } break;
            default: // Rounded Rect (simplified)
                ctx.roundRect(x - s / 2, y - s / 2, s, s, 4 * state.scale);
        }
        ctx.fill();

        if (state.scale > 0.8) {
            ctx.fillStyle = 'white';
            ctx.font = `bold ${10 * state.scale}px Inter, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(mc, x, y);
        }
    };

    const isTaskFinished = (status) => {
        return status === 'completed' || status === 'retries_failed';
    }

    // Animation Loop
    const render = () => {
        ctx.clearRect(0, 0, state.width, state.height);
        const now = Date.now();

        state.tasks.forEach((task, id) => {
            // Smooth movement (Lerp)
            task.currentX += (task.targetX - task.currentX) * 0.1;

            // Remove completed/failed due to retries tasks after some time
            if (isTaskFinished(task.status) && now - task.endTime > 5000) {
                state.tasks.delete(id);
                return;
            }

            drawShape(task.currentX * state.width, task.y * state.height, 24, task.mc, task.status);
        });

        // Enable/disable log panel depending the current task count
        syncLogPanelState();

        requestAnimationFrame(render);
    };
    requestAnimationFrame(render);

    /**
     * Synchronize terminal UI state with current task count
     * Only performs DOM operations when state actually changes
     */
    const syncLogPanelState = () => {
        const logPanelShouldBeDisabled = state.tasks.size > TASKS_LOG_THRESHOLD;

        if (state.isLogPanelDisabled !== logPanelShouldBeDisabled) {
            state.isLogPanelDisabled = logPanelShouldBeDisabled;

            // Use classList.toggle for clean state management
            DOM.log.classList.toggle('log-panel-disabled', state.isLogPanelDisabled);
        }
    };

    const handleUpdateTasks = (data) => {
        const { taskId, worker, mc, status, message } = data;

        // Throttled logging for performance
        if (state.tasks.size < TASKS_LOG_THRESHOLD) addLog(taskId, mc, status, message);

        if (!state.tasks.has(taskId)) {
            const y = 0.15 + Math.random() * 0.7;

            const jitterX = (Math.random() - 0.5) * 0.22;

            state.tasks.set(taskId, {
                mc: mc || state.mc,
                y: y,
                jitterX: jitterX,
                currentX: COORDS.queued + jitterX,
                targetX: COORDS.queued + jitterX,
                status: 'queued'
            });
        }

        const task = state.tasks.get(taskId);
        task.status = status;
        if (COORDS[status]) {
            task.targetX = COORDS[status] + task.jitterX;
        }
        if (status === 'completed') {
            task.endTime = Date.now();
            handleWorkerHeatmap(worker, false);
        } else if (status === 'retries_failed') {
            task.endTime = Date.now();
            handleWorkerHeatmap(worker, true);
        }
    };

    const startPinger = () => {
        stopPinger();

        state.pingTimer = setInterval(() => {
            if (state.ws?.readyState === WebSocket.OPEN) {
                state.ws.send(JSON.stringify({
                    event: 'ping',
                    data: { ts: performance.now() }
                }));
            }
        }, PING_INTERVAL_MS);
    }

    const stopPinger = () => {

        if (state.pingTimer) {
            clearInterval(state.pingTimer);
            state.pingTimer = null;
        }
    }

    // WebSocket & Logic (Minimal changes)
    const connect = () => {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws`;

        state.ws = new WebSocket(wsUrl);

        // open/close/error handling
        state.ws.onopen = (e) => {
            if (state.ws && state.ws.readyState === WebSocket.CONNECTING) {
                return;
            }

            console.log("%c REACTOR ONLINE ", "background: #064e3b; color: #10b981; font-weight: bold;");
            updateWsStatus(true);
            // Reset any reconnection attempts if successful
            state.reconnectAttempts = 0;

            startPinger();
        }
        state.ws.onclose = (e) => {
            updateWsStatus(false);
            console.log("%c REACTOR OFFLINE ", "background: #450a0a; color: #f87171; font-weight: bold;");

            stopPinger();

            // Clear tasks
            state.tasks.clear();

            // Reset worker heatmap
            document.getElementById("worker-heatmap").innerHTML = '';

            // Reset metrics
            const metrics = document.querySelectorAll('[data-default]');
            metrics.forEach(el => {
                el.textContent = el.dataset.default;
            });

            // Linear backoff: try to reconnect every 3 seconds
            // You can make it exponential if needed: Math.min(30000, (state.reconnectAttempts ** 2) * 1000)
            setTimeout(() => {
                state.reconnectAttempts = (state.reconnectAttempts || 0) + 1;
                console.log(`Reconnection attempt #${state.reconnectAttempts}...`);
                connect();
            }, 3000);
        }
        state.ws.onerror = (err) => {
            // ws.onclose will be called automatically after onerror
            state.ws.close();
        };

        state.ws.onmessage = (e) => {
            try {
                const msg = JSON.parse(e.data);
                const payload = msg.data;

                switch (msg.event) {
                    case 'welcome':
                        handleWelcome(payload);
                        break;

                    case 'status.changed':
                        handleUpdateTasks(payload);
                        break;

                    case 'metrics.update':
                        handleUpdateMetrics(payload);
                        break;

                    case 'pong':
                        handleUpdateLatency(payload);
                        break;
                }
            } catch (err) {
                console.error("Malformed message received", err);
            }
        };
    }

    const updateWsStatus = (online) => {
        const dot = document.getElementById("ws-status-dot");
        const text = document.getElementById("ws-status-text");

        if (!dot || !text) return;

        if (online) {
            dot.className = "w-1.5 h-1.5 rounded-full bg-green-500 shadow-[0_0_8px_#22c55e] transition-all";
            text.textContent = "Online";
            text.className = "text-green-400 font-mono text-[10px] uppercase";
        } else {
            dot.className = "w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse transition-all";
            text.textContent = "Disconnected";
            text.className = "text-red-500 font-mono text-[10px] uppercase";
        }
    };

    const handleUpdateLatency = (data) => {
        const latencyMs = Math.round(performance.now() - data.ts);

        document.getElementById("latency-display").textContent = `${latencyMs}ms`;
    }

    const handleUpdateMetrics = (data) => {
        // Basic metrics
        document.getElementById("memory-usage").textContent = data.memory_mb + 'MB';
        document.getElementById("connection-count").textContent = data.connections;
        document.getElementById("cpu-usage").textContent = data.cpu_usage + '%';

        handleUpdateQueueInfo(data.task_num);
        handleLODLogic(parseInt(data.task_num, 10));
    };

    const handleUpdateQueueInfo = (taskNum) => {
        if (!(state.system?.queue_capacity > 0)) {
            // Welcome message hasn't been received yet for some reason
            return;
        }

        const queueInfoEl = document.getElementById("queue-info");
        const taskNumEl = document.getElementById("task-num");

        // Critical load coloring
        if (taskNum / state.system.queue_capacity > 0.8) {
            queueInfoEl.classList.replace('text-yellow-500', 'text-red-500');
        } else {
            queueInfoEl.classList.replace('text-red-500', 'text-yellow-500');
        }

        taskNumEl.textContent = taskNum;
    }

    // Init state.system
    const handleWelcome = (data) => {
        state.system = data;

        // CPU cores
        document.getElementById("cpu-cores").textContent = state.system.cpu_cores;
        // Queue capacity
        document.getElementById("queue-capacity").textContent = `${Math.floor(state.system.queue_capacity / 1000)}k`;

        // Init heatmap based on worker_num
        const heatmap = document.getElementById("worker-heatmap");
        for (let i = 0; i < state.system.worker_num; i++) {
            const bar = document.createElement('div');
            // Added 'worker-bar' class for CSS animation targeting
            bar.className = "worker-bar w-3 h-1 bg-[#222] rounded-full";
            bar.setAttribute('data-worker', i);
            heatmap.appendChild(bar);
        }
    };

    const handleWorkerHeatmap = (activeWorker, isFailed = false) => {
        workerNum = state.system?.worker_num;

        if (typeof activeWorker === 'undefined' || workerNum === null) return;

        const workerIndex = activeWorker >= workerNum ? activeWorker - workerNum : activeWorker;
        const activeBar = document.querySelector(`#worker-heatmap [data-worker="${workerIndex}"]`);

        if (activeBar) {
            const flashClass = isFailed ? 'flash-error' : 'flash-success';

            // Restart animation trick
            activeBar.classList.remove('flash-success', 'flash-error');
            void activeBar.offsetWidth; // Force reflow
            activeBar.classList.add(flashClass);
        }
    };

    const handleLODLogic = (total) => {
        if (total <= 50) { state.scale = 1; state.mode = 'normal'; }
        else if (total <= 500) { state.scale = 0.5; state.mode = 'normal'; }
        else { state.scale = 0.3; state.mode = 'dot'; }
    };

    const showToast = (count, success, message) => {
        const toast = document.getElementById("reactorToast");
        if (!toast) return;

        // Set theme color using CSS variable
        const themeColor = success ? "#10b981" : "#ef4444";
        toast.style.setProperty('--toast-color', themeColor);

        // Build content: show count on success, or message on error
        const content = success
            ? `<b class="toast-brand">${count}</b> <span class="text-gray-300">tasks injected</span>`
            : `<b class="toast-brand">ERROR:</b> <span class="text-gray-300">${message}</span>`;

        toast.innerHTML = `
            <span class="text-gray-500 mr-2">REACTOR:</span>
            ${content}
        `;

        // Animation logic
        toast.classList.add("show");

        // Clear previous timeout if user clicks rapidly
        if (state.toastTimeout) clearTimeout(state.toastTimeout);

        state.toastTimeout = setTimeout(() => {
            toast.classList.remove("show");
        }, 1000);
    };


    // Controls
    DOM.mcSlider.oninput = (e) => {
        state.mc = e.target.value;
        DOM.mcDisplay.textContent = state.mc;
    };

    const createTasks = async (count) => {
        try {
            const response = await fetch("/api/tasks/create", {
                method: "POST",
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ count, max_concurrent: state.mc }),
            });

            const { success, message } = await response.json();

            // Check both HTTP status and your API's success flag
            showToast(count, response.ok && success, message);

        } catch (error) {
            // Handle network errors or parsing errors
            console.error("FAILED TO CREATE TASKS:", error);
            showToast(0, false, "Connection error");
        }
    };

    const initPipelineHud = () => {
        const hudContainer = document.getElementById('hud-shapes');
        const template = document.getElementById('hud-item-template');
        if (!hudContainer || !template) return;

        for (let i = 1; i <= 10; i++) {
            const clone = template.content.cloneNode(true);
            const wrapper = clone.querySelector('.hud-item');
            wrapper.setAttribute('data-mc', i);

            const shape = clone.querySelector('.hud-shape-preview');
            shape.textContent = i;

            hudContainer.appendChild(clone);
        }
    };

    document.querySelectorAll('.task-button').forEach(btn => {
        btn.onclick = () => {
            const count = parseInt(btn.getAttribute('data-count'), 10);
            if (count) createTasks(count);
        };
    });

    function addLog(taskId, mc, status, msg) {
        const entry = document.createElement('div');
        entry.className = 'whitespace-nowrap truncate text-[9px] leading-tight mb-0.5 opacity-80';
        const time = new Date().toLocaleTimeString([], { hour12: false });
        entry.innerHTML = `<span class="text-gray-600">${time}</span> <span class="text-blue-400 font-bold">[${status.toUpperCase()}]</span> <span class="text-white">${taskId.substring(0, 8)}</span> <span class="text-gray-400">${msg}</span>`;
        DOM.log.appendChild(entry);
        if (DOM.log.children.length > 30) DOM.log.removeChild(DOM.log.firstChild);
        DOM.log.scrollTop = DOM.log.scrollHeight;
    }

    initPipelineHud();
    connect();
});

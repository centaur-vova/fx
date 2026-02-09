document.addEventListener("DOMContentLoaded", () => {
    const state = {
        tasks: new Map(),
        workers: null,
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
        processing_progress: 0.625,
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

            // Optional: Debug log to track transitions
            // console.log(`[UI] Terminal state changed. Overloaded: ${state.isOverloaded}`);
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
                if (msg.event === "task.status.changed") handleUpdateTasks(msg.data);
                if (msg.event === "metrics.update") handleUpdateMetrics(msg.data);
                if (msg.event === "pong") handleUpdateLatency(msg.data);
            } catch (err) {
                console.error("Malformed message received", err);
            }
        };
    };

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
        const { queue, system } = data;

        // Basic metrics
        document.getElementById("memory-usage").textContent = system.memory_mb + 'Mb'
        document.getElementById("connection-count").textContent = system.connections;
        document.getElementById("cpu-load").textContent = system.cpu_percent + '%';

        // Queue info: "usage / max"
        const queueEl = document.getElementById("queue-info");
        queueEl.textContent = `${data.queue.usage} / ${Math.floor(data.queue.max / 1000)}k`;

        // Critical load coloring
        if (queue.usage / queue.max > 0.8) {
            queueEl.classList.replace('text-yellow-500', 'text-red-500');
        } else {
            queueEl.classList.replace('text-red-500', 'text-yellow-500');
        }

        handleLODLogic(parseInt(queue.usage, 10));
        handleWorkerHeatmapInit(system.workers);
    };

    // Init heatmap bars only once (fixed worker count, not dynamic)
    const handleWorkerHeatmapInit = (workers) => {
        // Init bars
        if (state.workers !== null || !workers) return;

        state.workers = workers;

        const heatmap = document.getElementById("worker-heatmap");
        for (let i = 0; i < workers; i++) {
            const bar = document.createElement('div');
            // Added 'worker-bar' class for CSS animation targeting
            bar.className = "worker-bar w-3 h-1 bg-[#222] rounded-full";
            bar.setAttribute('data-worker', i);
            heatmap.appendChild(bar);
        }
    };

    const handleWorkerHeatmap = (activeWorker, isFailed = false) => {
        if (typeof activeWorker === 'undefined' || !state.workers) return;

        const workerIndex = activeWorker >= state.workers ? activeWorker - state.workers : activeWorker;
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

            const data = await response.json();

            // Check both HTTP status and your API's success flag
            showToast(count, response.ok && data.success, data.message);

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

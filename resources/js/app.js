document.addEventListener("DOMContentLoaded", () => {
    const state = {
        tasks: new Map(),
        mc: 2,
        ws: null,
        scale: 1,
        mode: 'normal',
        width: 0,
        height: 0
    };

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
        lock_failed: 0.125
    };

    const DOM = {
        container: document.getElementById("pipeline"),
        log: document.getElementById("log-panel"),
        mcDisplay: document.getElementById("max-concurrent-display"),
        mcSlider: document.getElementById("max-concurrent-slider"),
    };

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
        ctx.globalAlpha = status === 'completed' ? 0.3 : 1;

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

    // Animation Loop
    const render = () => {
        ctx.clearRect(0, 0, state.width, state.height);
        const now = Date.now();

        state.tasks.forEach((task, id) => {
            // Smooth movement (Lerp)
            task.currentX += (task.targetX - task.currentX) * 0.1;

            // Remove completed tasks after some time
            if (task.status === 'completed' && now - task.endTime > 5000) {
                state.tasks.delete(id);
                return;
            }

            drawShape(task.currentX * state.width, task.y * state.height, 24, task.mc, task.status);
        });

        requestAnimationFrame(render);
    };
    requestAnimationFrame(render);

    const handleUpdateTasks = (data) => {
        const { taskId, mc, status, progress, message } = data;

        // Throttled logging for performance
        if (state.tasks.size < 300) addLog(taskId, mc, status, message);

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
        if (status === 'completed') task.endTime = Date.now();
    };

    // WebSocket & Logic (Minimal changes)
    const connect = () => {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        state.ws = new WebSocket(`${protocol}//${window.location.host}/ws`);
        state.ws.onopen = (e) => updateWsStatus(true);
        state.ws.onclose = (e) => {
            updateWsStatus(false);
            setTimeout(connect, 3000); // Auto-reconnect
        }
        state.ws.onmessage = (e) => {
            const msg = JSON.parse(e.data);
            if (msg.event === "task.status.changed") handleUpdateTasks(msg.data);
            if (msg.event === "metrics.update") handleUpdateMetrics(msg.data);
        };
    };

    const updateWsStatus = (online) => {
        const dot = document.getElementById("ws-status-dot");
        const text = document.getElementById("ws-status-text");

        if (online) {
            dot.className = "w-1.5 h-1.5 rounded-full bg-green-500 shadow-[0_0_8px_#22c55e]";
            text.textContent = "Online";
            text.className = "text-green-400 font-mono text-[10px] uppercase";
        } else {
            dot.className = "w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse";
            text.textContent = "Offline";
            text.className = "text-red-500 font-mono text-[10px] uppercase";
        }
    };

    const handleUpdateMetrics = (data) => {
        // Basic metrics
        document.getElementById("memory-usage").textContent = data.memory;
        document.getElementById("connection-count").textContent = data.connections;
        document.getElementById("cpu-load").textContent = data.cpu;

        // Queue info: "usage / max"
        const queueEl = document.getElementById("queue-info");
        if (queueEl && data.queue) {
            queueEl.textContent = `${data.queue.usage} / ${Math.floor(data.queue.max / 1000)}k`;

            // Critical load coloring
            if (data.queue.usage / data.queue.max > 0.8) {
                queueEl.classList.replace('text-yellow-500', 'text-red-500');
            } else {
                queueEl.classList.replace('text-red-500', 'text-yellow-500');
            }
        }

        handleLODLogic(parseInt(data.tasks, 10));
    };

    const handleLODLogic = (total) => {
        if (total <= 50) { state.scale = 1; state.mode = 'normal'; }
        else if (total <= 500) { state.scale = 0.5; state.mode = 'normal'; }
        else { state.scale = 0.3; state.mode = 'dot'; }
    };

    // Controls
    DOM.mcSlider.oninput = (e) => {
        state.mc = e.target.value;
        DOM.mcDisplay.textContent = state.mc;
    };

    const createTasks = (count) => {
        fetch("/api/tasks/create", {
            method: "POST",
            body: JSON.stringify({ count, max_concurrent: state.mc }),
        });
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

    connect();
});

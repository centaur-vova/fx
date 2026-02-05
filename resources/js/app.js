document.addEventListener("DOMContentLoaded", () => {
    const state = {
        tasks: {},
        mc: 2,
        ws: null,
    };

    const DOM = {
        pipeline: document.getElementById("pipeline"),
        log: document.getElementById("logPanel"),
        mcDisplay: document.getElementById("maxConcurrentDisplay"),
        mcSlider: document.getElementById("maxConcurrentSlider"),
    };

    const COORDS = {
        queued: 12.5,
        check_lock: 37.5,
        lock_acquired: 62.5,
        processing_progress: 62.5,
        completed: 87.5,
        lock_failed: 12.5,
    };

    // WebSocket Init
    const connect = () => {
        state.ws = new WebSocket(`ws://${window.location.hostname}:9501`);
        state.ws.onmessage = (e) => {
            const msg = JSON.parse(e.data);
            if (msg.event === "task.status.changed") handleUpdateTasks(msg.data);
            if (msg.event === "metrics.update") handleUpdateMetrics(msg.data);
        };
        state.ws.onopen = () => console.log("%c CONNECTED ", "background: green; color: white");
    };

    const updateGlobalScale = (totalTasks) => {
        const container = document.getElementById('pipeline');
        let scale = 1;
        let mode = 'normal';

        if (totalTasks <= 10) {
            scale = 1.66;
        } else if (totalTasks <= 50) {
            scale = 1;
        } else if (totalTasks <= 200) {
            scale = 0.75;
        } else if (totalTasks <= 500) {
            scale = 0.5;
        } else if (totalTasks <= 1000) {
            scale = 0.3;
            mode = 'dot';
        } else {
            scale = 0.2;
            mode = 'dot';
        }

        if (container) {
            container.style.setProperty('--task-scale', scale);
            container.setAttribute('data-view-mode', mode);
        }
    }

    const handleUpdateMetrics = (data) => {
        const memEl = document.getElementById("memory-usage");
        const connEl = document.getElementById("connection-count");
        const cpuEl = document.getElementById("cpu-load");
        const tasksEl = document.getElementById("tasks-count");
        const usageEl = document.getElementById("queue-usage");
        const maxEl = document.getElementById("queue-max");

        if (memEl) memEl.textContent = data.memory;
        if (connEl) connEl.textContent = data.connections;
        if (cpuEl) cpuEl.textContent = data.cpu;
        if (tasksEl) tasksEl.textContent = data.tasks;

        if (usageEl && data.queue) {
            const usage = parseInt(data.queue.usage, 10);
            const max = parseInt(data.queue.max, 10);

            usageEl.textContent = usage;
            if (maxEl) maxEl.textContent = max;

            if (usage / max > 0.9) {
                usageEl.classList.add('text-red-500');
                usageEl.classList.remove('text-yellow-500');
            } else {
                usageEl.classList.add('text-yellow-500');
                usageEl.classList.remove('text-red-500');
            }
        }

        updateGlobalScale(parseInt(data.tasks, 10));
    }

    const handleUpdateTasks = (data) => {
        const { taskId, mc, status, progress, message } = data;
        addLog(taskId, mc, status, message);

        if (!state.tasks[taskId]) createTask(taskId, mc || state.mc);
        updateTask(taskId, status, progress);
    };

    const createTask = (id, mc) => {
        const el = document.createElement("div");
        el.id = `task-${id}`;
        el.className = `task task-concurrent-${mc}`;
        el.textContent = mc;

        const top = 20 + Math.random() * 60;
        const jitterX = (Math.random() - 0.5) * 12;

        el.style.top = `${top}%`;
        el.style.left = `${COORDS.queued + jitterX}%`;

        DOM.pipeline.appendChild(el);
        state.tasks[id] = { el, status: "queued", top, jitterX };
    };

    const updateTask = (id, status, mc) => {
        const task = state.tasks[id];
        if (!task) return;

        if (COORDS[status]) {
            const targetLeft = COORDS[status] + (task.jitterX || 0);

            if (targetLeft !== task.lastCoord) {
                task.el.style.left = targetLeft + "%";
                task.lastCoord = targetLeft;
            }
        }

        if (status === "completed") completeTask(id);
    };

    const completeTask = (id) => {
        const task = state.tasks[id];
        if (!task) return;

        task.el.classList.add("completed");

        setTimeout(() => {
            if (task.el && task.el.parentNode) {
                task.el.style.opacity = '0';
                task.el.style.transform = 'translate(-50%, -50%) scale(0)';

                setTimeout(() => {
                    task.el.remove();
                    delete state.tasks[id];
                }, 300);
            }
        }, 5000);
    };

    function addLog(taskId, mc, status, msg) {
        const entry = document.createElement('div');
        entry.className = 'whitespace-nowrap truncate text-[10px] leading-tight mb-0.5';

        const time = new Date().toLocaleTimeString([], { hour12: false });
        const shortId = taskId.replace('task-', '').substring(0, 8);

        const statusStyles = {
            'queued': 'text-gray-400',
            'check_lock': 'text-amber-500',
            'lock_acquired': 'text-yellow-400',
            'lock_failed': 'text-red-500 animate-pulse',
            'processing': 'text-blue-500',
            'processing_progress': 'text-sky-400',
            'completed': 'text-green-500',
            'default': 'text-gray-500'
        };

        const colorClass = statusStyles[status] || statusStyles['default'];
        const displayStatus = status ? status.toUpperCase().replace('_', ' ') : 'INFO';


        entry.innerHTML =
            `<span class="text-gray-600">${time}</span> ` +
            `<span class="${colorClass} font-bold">[${displayStatus}]</span> ` +
            `<span class="text-gray-500">ID:</span><span class="text-white">${shortId}</span> ` +
            `<span class="text-gray-300 ml-1"> ${msg}</span>`;

        DOM.log.appendChild(entry);

        if (DOM.log.children.length > 50) {
            DOM.log.removeChild(DOM.log.firstChild);
        }
        DOM.log.scrollTop = DOM.log.scrollHeight;
    }

    // Listeners
    DOM.mcSlider.oninput = (e) => {
        state.mc = e.target.value;
        DOM.mcDisplay.textContent = state.mc;
    };

    [1, 5, 20, 50, 100].forEach((count) => {
        const btnId =
            count === 1
                ? "createOneBtn"
                : count === 5
                    ? "createFiveBtn"
                    : count === 20
                        ? "createTwentyBtn"
                        : count === 50
                            ? "createFiftyBtn"
                            : "createHundredBtn";
        document.getElementById(btnId).onclick = () => {
            fetch("/api/tasks/create", {
                method: "POST",
                body: JSON.stringify({ count, max_concurrent: state.mc }),
            });
        };
    });

    connect();
});

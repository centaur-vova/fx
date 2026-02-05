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

    const handleUpdateMetrics = (data) => {
        const memEl = document.getElementById("memory-usage");
        const connEl = document.getElementById("connection-count");
        const cpuEl = document.getElementById("cpu-load");
        const tasksEl = document.getElementById("tasks-count");

        if (memEl) memEl.textContent = data.memory;
        if (connEl) connEl.textContent = data.connections;
        if (cpuEl) cpuEl.textContent = data.cpu;
        if (tasksEl) tasksEl.textContent = data.tasks;
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

        if (status === "completed") task.el.classList.add("completed");
    };

    function addLog(taskId, mc, status, msg) {
        const entry = document.createElement('div');
        entry.className = 'whitespace-nowrap truncate';

        const time = new Date().toLocaleTimeString([], { hour12: false });
        const shortStatus = status ? status.toUpperCase() : 'INFO';
        const msgText = msg ? msg.toUpperCase() : 'INFO';

        entry.innerHTML = `<span class="text-gray-600">${time}</span> ` +
            `<span class="text-yellow-500">[${shortStatus}]</span> ` +
            `<span class="text-white font-bold">${taskId}</span> ` +
            `<span class="text-green-500">${msgText}</span>`;

        DOM.log.appendChild(entry);

        while (DOM.log.children.length > 40) {
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

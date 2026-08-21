import { TERMINAL_LOG } from "./config";

class TerminalLog {
    constructor() {
        this.container = document.getElementById("log-panel");
        this.buffer = [];
        this.flushTimer = null;
    }

    add(data, taskCount) {
        if (!this.container) return;

        // Don't add to buffer when above threshold
        if (taskCount > TERMINAL_LOG.DISABLED_THRESHOLD) return;

        this.buffer.push(data);

        if (!this.flushTimer) {
            this.flushTimer = setTimeout(() => this.flush(), TERMINAL_LOG.FLUSH_INTERVAL_MS);
        }
    }

    flush() {
        if (!this.buffer.length || !this.container) {
            this.flushTimer = null;
            return;
        }

        const fragment = document.createDocumentFragment();

        for (const data of this.buffer) {
            const { id, status, message, sem, progress } = data;
            const entry = document.createElement('div');
            entry.className = 'whitespace-nowrap truncate text-[9px] leading-tight mb-0.5 opacity-80';

            const time = new Date().toLocaleTimeString([], { hour12: false }); // ← ВНУТРИ ЦИКЛА
            const statusColor = TERMINAL_LOG.STATUS_COLORS[status] || 'var(--text-muted)';
            const semLabel = sem === 1 ? 'API' : 'PHP';
            const paddedStatus = status.toUpperCase().padEnd(14, ' ');
            const hexId = (id & 0xFFFF).toString(16).toUpperCase().padStart(4, '0');
            const progressText = progress > 0 ? ` (${progress}%)` : '';

            entry.innerHTML = `
            <span style="color: var(--text-secondary)">${time}</span>
            <span style="color: ${statusColor}; font-weight: bold; white-space: pre">${semLabel} » ${paddedStatus}</span>
            <span style="color: var(--text-primary)">${hexId}</span>
            <span style="color: var(--text-muted)">${message}${progressText}</span>
        `;

            fragment.appendChild(entry);
        }

        const incoming = this.buffer.length;

        // Remove old entries if total would overflow
        while (this.container.children.length + incoming > TERMINAL_LOG.MAX_LOG_ENTRIES && this.container.firstChild) {
            this.container.removeChild(this.container.firstChild);
        }

        this.container.appendChild(fragment);
        this.container.scrollTop = this.container.scrollHeight;

        this.buffer = [];
        this.flushTimer = null;
    }
}

export default new TerminalLog();
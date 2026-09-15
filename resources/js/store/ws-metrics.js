export const wsMetrics = {
    wsMetrics: {
        totalMessages: 0,
        rps: 0,
        _counter: 0,
        _lastTime: performance.now(),
    },

    /**
     * Track a single WebSocket message.
     * Call this on every ws.onmessage.
     */
    trackWsMessage() {
        const m = this.wsMetrics;
        m.totalMessages++;
        m._counter++;

        const now = performance.now();
        const deltaTime = (now - m._lastTime) / 1000;

        if (deltaTime >= 1.0) {
            m.rps = Math.round(m._counter / deltaTime);
            m._counter = 0;
            m._lastTime = now;
        }
    },

    resetWsMetrics() {
        this.wsMetrics = {
            totalMessages: 0,
            rps: 0,
            _counter: 0,
            _lastTime: performance.now(),
        };
    },
};
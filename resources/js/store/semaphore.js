export const semaphore = {
    semaphoreMetrics: {
        acquireRps: 0,
        retryRps: 0,
        totalAcquires: 0,
        totalRetries: 0,
        _acquireHistory: [],
        _retryHistory: [],
        _acquireCounter: 0,
        _retryCounter: 0,
        _lastMetricsTime: performance.now(),
    },

    incrementAcquire() {
        this.semaphoreMetrics._acquireCounter++;
        this.semaphoreMetrics.totalAcquires++;
    },

    incrementRetry() {
        this.semaphoreMetrics._retryCounter++;
        this.semaphoreMetrics.totalRetries++;
    },

    updateSemaphoreRps() {
        const m = this.semaphoreMetrics;
        const now = performance.now();
        const deltaTime = (now - m._lastMetricsTime) / 1000;

        if (deltaTime < 1.0) return;

        const acquireRps = Math.round(m._acquireCounter / deltaTime);
        const retryRps = Math.round(m._retryCounter / deltaTime);

        const WINDOW = 5;
        m._acquireHistory.push(acquireRps);
        m._retryHistory.push(retryRps);
        if (m._acquireHistory.length > WINDOW) m._acquireHistory.shift();
        if (m._retryHistory.length > WINDOW) m._retryHistory.shift();

        const avg = (arr) => arr.length > 0
            ? Math.round(arr.reduce((a, b) => a + b, 0) / arr.length)
            : 0;

        m.acquireRps = avg(m._acquireHistory);
        m.retryRps = avg(m._retryHistory);

        m._acquireCounter = 0;
        m._retryCounter = 0;
        m._lastMetricsTime = now;
    },

    resetSemaphoreMetrics() {
        this.semaphoreMetrics = {
            acquireRps: 0,
            retryRps: 0,
            totalAcquires: 0,
            totalRetries: 0,
            _acquireHistory: [],
            _retryHistory: [],
            _acquireCounter: 0,
            _retryCounter: 0,
            _lastMetricsTime: performance.now(),
        };
    },
};
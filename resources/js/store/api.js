import { ROUTES } from "../modules/config";

export const api = {
    api: {
        instances: [],
        stats: {
            up: 0,
            down: 0,
            totalRequests: 0,
            totalErrors: 0,
        },
    },

    async fetchBalancerHealth() {
        try {
            const response = await fetch(ROUTES.HEALTH);
            const { data } = await response.json();

            if (data?.status === 'ok') {
                this.api.instances = data.balancer?.instances || [];
                this.api.stats = {
                    up: data.balancer.up || 0,
                    down: data.balancer.down || 0,
                    totalRequests: data.balancer.total_requests || 0,
                    totalErrors: data.balancer.total_errors || 0,
                };
            }
        } catch (e) {
            console.warn('Failed to fetch balancer health:', e);
        }
    },

    startBalancerPoller(intervalMs = 1000) {
        if (this.balancerPoller) clearInterval(this.balancerPoller);

        this.fetchBalancerHealth();
        this.balancerPoller = setInterval(() => {
            if (this.isOnline) {
                this.fetchBalancerHealth();
            }
        }, intervalMs);
    },

    stopBalancerPoller() {
        if (this.balancerPoller) {
            clearInterval(this.balancerPoller);
            this.balancerPoller = null;
        }
    },

    async toggleApi(inst) {
        try {
            inst.cb_state = 'half-open';
            const alive = inst.alive;

            const response = await fetch(ROUTES.API.INSTANCE_TOGGLE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ hash: inst.hash, alive: !alive }),
            });
            const data = await response.json();

            if (data.success) {
                setTimeout(() => this.fetchBalancerHealth(), 100);
            } else {
                this.showToast(data.error || ('Failed to ' + (alive ? 'disable' : 'enable') + ' instance'), false);
            }
        } catch (e) {
            this.showToast('Connection error', false);
        }
    },
};
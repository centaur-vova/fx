export const generators = {
    // Icy blues, purples, silver (crystal)
    ice: (mc) => {
        const hue = (mc * 15) % 360;
        return `hsl(${hue}, 70%, 65%)`;
    },

    // Hot neon colors (fast)
    neon: (mc) => {
        const hue = (mc * 27) % 360;
        return `hsl(${hue}, 85%, 55%)`;
    },

    // Cold blues, cyans (frozen)
    arctic: (mc) => {
        const hue = 180 + (mc * 8) % 60;
        return `hsl(${hue}, 80%, 60%)`;
    },

    // Mostly gray, occasional red (sin-city)
    noir: (mc) => {
        if (mc % 10 === 0 || mc > 50) return '#ff3333';
        const gray = 100 + (mc % 100);
        return `rgb(${gray}, ${gray}, ${gray})`;
    },

    // Warm, faded colors (vintage)
    vintage: (mc) => {
        const hue = 40 + (mc * 5) % 50;
        return `hsl(${hue}, 60%, 55%)`;
    },

    // Rainbow spectrum (default)
    rainbow: (mc) => {
        const hue = (mc * 25) % 360;
        return `hsl(${hue}, 75%, 60%)`;
    }
};

// Default fallback
export const defaultGenerator = generators.rainbow;
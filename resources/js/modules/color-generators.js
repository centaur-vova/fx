export const generators = {
    // Icy blues, purples, silver (crystal)
    ice: (seed) => {
        const hue = (seed * 15) % 360;
        return `hsl(${hue}, 70%, 65%)`;
    },

    // Hot neon colors (fast)
    neon: (seed) => {
        const hue = (seed * 27) % 360;
        return `hsl(${hue}, 85%, 55%)`;
    },

    // Cold blues, cyans (frozen)
    arctic: (seed) => {
        const hue = 180 + (seed * 8) % 60;
        return `hsl(${hue}, 80%, 60%)`;
    },

    // Mostly gray, occasional red (sin-city)
    noir: (seed) => {
        if (seed % 10 === 0 || seed > 200) return '#ff3333';
        const gray = 100 + (seed % 100);
        return `rgb(${gray}, ${gray}, ${gray})`;
    },

    // Warm, faded colors (vintage)
    vintage: (seed) => {
        const hue = 40 + (seed * 5) % 50;
        return `hsl(${hue}, 60%, 55%)`;
    },

    // Rainbow spectrum (default)
    rainbow: (seed) => {
        const hue = (seed * 25) % 360;
        return `hsl(${hue}, 75%, 60%)`;
    },

    // Borderlands: cel-shaded chaos. Flat bright colors, black outlines elsewhere.
    borderlands: (seed) => {
        const palette = [
            '#ffcc00',  // yellow (primary)
            '#ff3333',  // red
            '#ffffff',  // white
            '#ff6600',  // orange
            '#00ccff',  // cyan (rare)
            '#ff99cc',  // pink (rare)
            '#99ff33',  // lime (rare)
        ];
        return palette[seed % palette.length];
    },

    // Matrix: classic phosphor green. Subtle drift, bright and saturated.
    matrix: (seed) => {
        const hue = 120 + (seed % 15);        // 120–135, green with slight drift
        const lightness = 50 + (seed % 30);   // 50–80%
        const saturation = 90 + (seed % 10);  // 90–100%
        return `hsl(${hue}, ${saturation}%, ${lightness}%)`;
    },

    // Matrix-2: blue vs red. Two families, distinct ranges.
    'matrix-2': (seed) => {
        const family = seed % 2;
        const hue = family === 0 ? 210 : 0;   // blue or red
        const lightness = family === 0
            ? 55 + (seed % 25)   // blue: 55–80%, bright
            : 45 + (seed % 25);  // red: 45–70%, deep
        const saturation = family === 0
            ? 90 + (seed % 10)   // blue: 90–100%
            : 85 + (seed % 15);  // red: 85–100%
        return `hsl(${hue}, ${saturation}%, ${lightness}%)`;
    },
};

// Default fallback
export const defaultGenerator = generators.rainbow;
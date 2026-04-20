import {
    COLORS,
    UI,
} from './config';

/**
 * Draw a single task square (or dot) on the canvas.
 * @param {CanvasRenderingContext2D} ctx - Canvas context
 * @param {number} x - center X coordinate
 * @param {number} y - center Y coordinate
 * @param {number} size - base size (before scaling)
 * @param {Object} task - task object with properties: mc, status, progress, pulseOffset
 * @param {string} mode - display mode ('normal' or 'dot')
 * @param {number} scale - current global scale factor
 */
export const drawShape = (ctx, x, y, size, task, mode, scale) => {
    const { mc, status, pulseOffset, progress = null } = task;
    const s = size * scale;
    const isFinished = status === 'completed' || status === 'retries_failed';

    let fillColor = COLORS[mc] || '#ffffff';
    ctx.fillStyle = fillColor;

    // Pulsation for 'progress' tasks (all squares pulse together)
    if (status === 'progress' && scale > UI.PROGRESS_BAR_MIN_SCALE) {
        const speed = 150; // common speed
        const pulse = 0.6 + 0.4 * Math.sin((Date.now() / speed) + (task.pulseOffset || 0));
        ctx.globalAlpha = pulse;
    } else {
        ctx.globalAlpha = isFinished ? 0.3 : 1;
    }

    if (mode === 'dot') {
        ctx.beginPath();
        ctx.arc(x, y, 2 * scale, 0, Math.PI * 2);
        ctx.fill();
        return;
    }

    ctx.beginPath();
    ctx.roundRect(x - s / 2, y - s / 2, s, s, 4 * scale);
    ctx.fill();

    ctx.globalAlpha = 1;

    if (scale > UI.PROGRESS_BAR_MIN_SCALE) {
        ctx.fillStyle = 'white';
        ctx.font = `bold ${10 * scale}px Inter, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(mc, x, y);

        if (status === 'progress' && mode !== 'dot' && progress && progress > 0 && progress < 100) {
            ctx.fillStyle = UI.PROGRESS_BAR_COLOR;
            ctx.fillRect(x - s / 2, y + s / 2 - UI.PROGRESS_BAR_HEIGHT, s * (progress / 100), UI.PROGRESS_BAR_HEIGHT);
        }
    }
};

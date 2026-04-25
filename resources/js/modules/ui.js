import { COLORS, LABEL_COLORS, PROGRESS_BAR } from './config';

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
    if (scale > PROGRESS_BAR.min_scale) {
        if (status === 'progress') {
            const speed = 150;
            const pulse = 0.6 + 0.4 * Math.sin((Date.now() / speed) + (task.pulseOffset || 0));
            ctx.globalAlpha = pulse;
        } else if (status === 'queued') {
            ctx.globalAlpha = 0.8;
        } else if (status === 'check_lock') {
            ctx.globalAlpha = 0.6;
        } else {
            ctx.globalAlpha = isFinished ? 0.3 : 1;
        }
    } else {
        ctx.globalAlpha = isFinished ? 0.3 : 1;
    }

    if (mode === 'dot') {
        // Just a pixel — fastest possible
        ctx.fillStyle = fillColor;
        ctx.fillRect(x, y, 1, 1);
        return;
    }

    ctx.beginPath();
    ctx.roundRect(x - s / 2, y - s / 2, s, s, 4 * scale);
    ctx.fill();

    ctx.globalAlpha = 1;

    if (scale > PROGRESS_BAR.min_scale) {
        ctx.fillStyle = LABEL_COLORS[mc];
        ctx.font = `bold ${10 * scale}px Inter, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(mc, x, y);

        if (status === 'progress' && progress && progress > 0 && progress < 100 && PROGRESS_BAR.height > 0) {
            ctx.fillRect(x - s / 2, y + s / 2 - PROGRESS_BAR.height, s * (progress / 100), PROGRESS_BAR.height);
        }
    }
};

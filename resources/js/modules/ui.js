import { COLORS, LABEL_COLORS, LOD, PROGRESS_BAR, TASK_STATUS } from './config';

/**
 * Draw a single task square (or dot) on the canvas.
 * @param {CanvasRenderingContext2D} ctx - Canvas context
 * @param {number} x - center X coordinate
 * @param {number} y - center Y coordinate
 * @param {number} size - base size (before scaling)
 * @param {Object} task - task object with getLabel() method
 * @param {string} mode - display mode ('normal' or 'dot')
 * @param {number} scale - current global scale factor
 */
export const drawShape = (ctx, x, y, size, task, mode, scale) => {
    if (mode === 'dot') {
        ctx.fillRect(x, y, 1, 1);
        return;
    }

    const { status, sem, progress = null } = task;

    // Size scales with position: small at left, large at right
    const sizeScale = 0.5 + 0.8 * task.currentX;
    const s = size * scale * sizeScale;

    const fillColor = task.getColor();
    ctx.fillStyle = fillColor;
    // Set base alpha (position-based fade-in)
    setAlpha(ctx, task);

    // Apply exit alpha if task is in exit animation
    if (task._exitAlpha !== undefined) {
        ctx.globalAlpha *= task._exitAlpha;
    }

    const showLabel = scale > LOD.scale_medium;

    if (!showLabel) {
        drawTaskBackground(ctx, x, y, s / 2, s / 2, sem, scale);
        ctx.globalAlpha = 1;

        if (shouldDrawProgressBar(scale, status, progress)) {
            drawProgressBar(ctx, x, y, s, s / 2, progress, scale);
        }
        return;
    }

    drawTaskBackground(ctx, x, y, s / 2, s / 2, sem, scale);
    ctx.globalAlpha = 1;

    const label = task.getLabel();
    const fontSize = Math.min(10 * scale, s * 0.5);
    ctx.font = `bold ${fontSize}px Inter, sans-serif`;
    ctx.fillStyle = LABEL_COLORS[task.mc] || '#ffffff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(label, x, y);

    if (shouldDrawProgressBar(scale, status, progress)) {
        drawProgressBar(ctx, x, y, s, s / 2, progress, scale);
    }
};

const setAlpha = (ctx, task) => {
    // Sharp ramp: 0.1 at left, 1.0 at right, with power curve
    const raw = Math.pow(task.currentX, 0.5); // 0.0 → 0.0, 1.0 → 1.0, but steeper
    const alpha = 0.05 + 0.95 * raw;
    ctx.globalAlpha = Math.min(alpha, 1);
};

const drawTaskBackground = (ctx, x, y, halfWidth, halfHeight, sem, scale) => {
    if (sem === 1) {
        ctx.beginPath();
        ctx.roundRect(x - halfWidth, y - halfHeight, halfWidth * 2, halfHeight * 2, 4 * scale);
        ctx.fill();
    } else {
        ctx.fillRect(x - halfWidth, y - halfHeight, halfWidth * 2, halfHeight * 2);
    }
};

const drawLabel = (ctx, x, y, label, task, scale) => {
    ctx.fillStyle = LABEL_COLORS[task.mc];
    ctx.font = `bold ${10 * scale}px Inter, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(label, x, y);
};

const shouldDrawProgressBar = (scale, status, progress) => {
    return scale >= PROGRESS_BAR.min_scale &&
        status === TASK_STATUS.PROGRESS &&
        progress && progress > 0 && progress < 100 &&
        PROGRESS_BAR.height > 0;
};

const drawProgressBar = (ctx, x, y, blockWidth, halfHeight, progress, scale) => {
    const progressWidth = blockWidth * (progress / 100);
    const barHeight = PROGRESS_BAR.height;
    const barX = x - blockWidth / 2;
    const barY = y + halfHeight - barHeight;

    ctx.fillRect(barX, barY, progressWidth, barHeight);
};
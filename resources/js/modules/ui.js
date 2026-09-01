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
    // Early exit: dot mode
    if (mode === 'dot') {
        ctx.fillRect(x, y, 1, 1);
        return;
    }

    const { status, sem, progress = null } = task;
    const s = size * scale;

    const showLabel = scale > LOD.scale_medium;

    // Medium LOD — no label, just square
    if (!showLabel) {
        const fillColor = task.getColor();
        ctx.fillStyle = fillColor;
        setAlpha(ctx, task);
        drawTaskBackground(ctx, x, y, s / 2, s / 2, sem, scale);
        ctx.globalAlpha = 1;

        if (shouldDrawProgressBar(scale, status, progress)) {
            drawProgressBar(ctx, x, y, s, s / 2, progress, scale);
        }
        return;
    }

    // Full LOD — with label
    const label = task.getLabel ? task.getLabel() : (task.mc?.toString() || '?');
    ctx.font = `bold ${10 * scale}px Inter, sans-serif`;
    const textWidth = ctx.measureText(label).width;
    const paddingX = 12 * scale;
    const blockWidth = Math.max(s, textWidth + paddingX * 2);
    const blockHeight = s;
    const halfWidth = blockWidth / 2;
    const halfHeight = blockHeight / 2;

    const fillColor = task.getColor();
    ctx.fillStyle = fillColor;
    setAlpha(ctx, task);

    drawTaskBackground(ctx, x, y, halfWidth, halfHeight, sem, scale);
    ctx.globalAlpha = 1;

    drawLabel(ctx, x, y, label, task, scale);

    if (shouldDrawProgressBar(scale, status, progress)) {
        drawProgressBar(ctx, x, y, blockWidth, halfHeight, progress, scale);
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
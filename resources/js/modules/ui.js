import { COLORS, LABEL_COLORS, LOD, PROGRESS_BAR } from './config';

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
    const { status, sem, progress = null } = task;
    const s = size * scale;
    const isFinished = task.isFinished?.() || status === 'completed' || status === 'retries_failed';

    // Get label and measure its width
    const label = task.getLabel ? task.getLabel() : (task.mc?.toString() || '?');

    // Set font for measurement
    ctx.font = `bold ${10 * scale}px Inter, sans-serif`;
    const textWidth = ctx.measureText(label).width;

    // Calculate block dimensions based on text width
    const paddingX = 12 * scale;
    const paddingY = 0;
    const blockWidth = Math.max(s, textWidth + paddingX * 2);
    const blockHeight = s + paddingY * 2;
    const halfWidth = blockWidth / 2;
    const halfHeight = blockHeight / 2;

    // Set color
    let fillColor = task.getColor ? task.getColor() : (COLORS[task.mc] || '#ffffff');
    ctx.fillStyle = fillColor;

    // Alpha / pulsation
    setAlpha(ctx, task, status, isFinished, scale);

    if (mode === 'dot') {
        ctx.fillRect(x, y, 1, 1);
        return;
    }

    // Draw background
    drawTaskBackground(ctx, x, y, halfWidth, halfHeight, sem, scale);

    ctx.globalAlpha = 1;

    // Draw label
    if (scale > LOD.scale_medium) {
        drawLabel(ctx, x, y, label, task, scale);
    }

    // Draw progress bar
    if (shouldDrawProgressBar(scale, status, progress)) {
        drawProgressBar(ctx, x, y, blockWidth, halfHeight, progress, scale);
    }
};

const setAlpha = (ctx, task, status, isFinished, scale) => {
    const alphaScale = scale > PROGRESS_BAR.min_scale;

    if (!alphaScale) {
        ctx.globalAlpha = isFinished ? 0.3 : 1;
        return;
    }

    switch (status) {
        case 'progress':
            const speed = 150;
            const pulse = 0.6 + 0.4 * Math.sin((Date.now() / speed) + (task.pulseOffset || 0));
            ctx.globalAlpha = pulse;
            break;
        case 'queued':
            ctx.globalAlpha = 0.8;
            break;
        case 'check_lock':
            ctx.globalAlpha = 0.6;
            break;
        default:
            ctx.globalAlpha = isFinished ? 0.3 : 1;
    }
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
    ctx.fillStyle = task.getColor ? task.getColor() : (LABEL_COLORS[task.mc] || '#ffffff');
    ctx.font = `bold ${10 * scale}px Inter, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(label, x, y);
};

const shouldDrawProgressBar = (scale, status, progress) => {
    return scale >= PROGRESS_BAR.min_scale &&
        status === 'progress' &&
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
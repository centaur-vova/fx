import { drawShape } from './ui.js';

/**
 * Creates the main render loop for task visualization.
 *
 * @param {CanvasRenderingContext2D} ctx
 * @param {Map} tasks
 * @param {Object} store
 * @returns {Function} render function to be passed to requestAnimationFrame
 */
export const createRenderer = (ctx, tasks, store) => {
    const render = () => {
        requestAnimationFrame(render);

        if (!store.renderEnabled) {
            return;
        }

        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);

        const now = Date.now();
        const { width, mode, scale } = store;
        const baseSize = 24 * scale;

        tasks.forEach((task, id) => {
            task.currentX += (task.targetX - task.currentX) * 0.1;

            if (task.isExpired(now)) {
                tasks.delete(id);
                return;
            }

            drawShape(
                ctx,
                task.currentX * width,
                task.y * store.height,
                baseSize,
                task,
                mode,
                scale
            );
        });

        store.updateTaskNum(tasks.size);
    };

    return render;
};
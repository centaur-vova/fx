import { drawShape } from './ui.js';
import { TASK_STATUS } from './config.js';

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
        const nowSec = now / 1000;
        const { width, mode, scale } = store;
        const baseSize = 24 * scale;

        tasks.forEach((task, id) => {
            if (task.isExpired(now)) {
                tasks.delete(id);
                return;
            }

            if (task.status === TASK_STATUS.PROGRESS && task.wave) {
                task.wave.apply(task, nowSec);
            } else {
                // Regular linear motion for all other statuses
                task.currentX += (task.targetX - task.currentX) * 0.1;
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

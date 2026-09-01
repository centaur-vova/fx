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

            // Handle exit animation
            if (task.exitSpeed !== 0) {
                task.y += task.exitSpeed;
                task.exitSpeed += 0.0006 * Math.sign(task.exitSpeed); // gravity always downward

                const distance = Math.abs(task.y - 0.5);
                const maxDistance = 0.7;
                task._exitAlpha = Math.max(0, 1 - distance / maxDistance);

                if (task.y < -0.3 || task.y > 1.3) {
                    tasks.delete(id);
                    return;
                }
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

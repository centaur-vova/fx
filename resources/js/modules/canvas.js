/**
 * Sets up a canvas element inside a container with responsive resizing.
 *
 * The canvas adapts to the container's dimensions, handles device pixel ratio,
 * and automatically re-renders on resize.
 *
 * @param {HTMLElement} container - The DOM element that holds the canvas
 * @param {Object} store - The Alpine store containing width/height state
 * @param {CanvasRenderingContext2D} ctx - The 2D rendering context of the canvas
 * @returns {ResizeObserver} The ResizeObserver instance for cleanup if needed
 */
export const setupCanvas = (container, store, ctx) => {
    const resize = () => {
        const w = container.clientWidth || window.innerWidth;
        const h = container.clientHeight || 500;

        store.width = w;
        store.height = h;

        const canvas = ctx.canvas;
        canvas.width = w * window.devicePixelRatio;
        canvas.height = h * window.devicePixelRatio;
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';

        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
    };

    const ro = new ResizeObserver(resize);
    ro.observe(container);

    // And call resize() just to make sure
    resize();

    return ro;
};
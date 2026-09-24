import { LABEL_COLORS, TASK_BTN_MODE } from '../modules/config.js';
import { getThemeColor } from '../modules/theme-config.js';
import { drawShape } from '../modules/ui.js';
import { Task } from '../modules/task-store.js';
import { getFlowDefaults } from './defaults.js';

export const flow = {
    mc: 2,
    mode: 'normal',
    scale: 1,
    disableMcSlider: false,
    hideMcSlider: false,
    previewSem: 0, // squared cornerz by default
    flow: getFlowDefaults(),

    initFlow() {
        const themeFlow = window.THEME_CONFIG?.settings?.flow || {};

        this.flow.min = themeFlow.min_concurrent || 1;
        this.flow.max = themeFlow.max_concurrent || 10;
        this.mc = themeFlow.default_concurrent || this.flow.min;

        // Read hide_mc_slider flag
        this.hideMcSlider = themeFlow.hide_mc_slider || false;

        if (themeFlow.task_buttons?.length > 0) {
            this.flow.buttons = themeFlow.task_buttons.map(btn => ({
                label: btn.label || (btn.tasks || btn).toString(),
                tasks: btn.tasks ?? btn,
                stress: !!btn.stress,
                mode: btn.mode || TASK_BTN_MODE.NORMAL,
                class: btn.class || 'default',
                full_width: btn.full_width || false,
                semaphore_driver: btn.semaphore_driver || 'shared',
                tooltip: btn.tooltip || '',
            }));
        }
    },

    renderMcPreview() {
        const canvas = document.getElementById('mc-preview');
        if (!canvas) return;

        const dpr = window.devicePixelRatio || 1;
        const cssSize = 32;
        const size = cssSize * dpr;

        canvas.width = size;
        canvas.height = size;
        canvas.style.width = `${cssSize}px`;
        canvas.style.height = `${cssSize}px`;

        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, size, size);

        const task = Task.preview(this.mc, this.previewSem);
        drawShape(ctx, size / 2, size / 2, size * 0.5, task, 'normal', 1.2, true);
    },

    onTaskButtonHover(btn, state) {
        this.disableMcSlider = state && (btn.mode === TASK_BTN_MODE.NITRO || btn.mode === TASK_BTN_MODE.RAND);

        if (state && !this.disableMcSlider && btn.semaphore_driver) {
            this.previewSem = btn.semaphore_driver === 'api' ? 1 : 0;
        }
    },

    getMcColor() {
        return getThemeColor(this.mc);
    },

    getLabelColor() {
        return LABEL_COLORS[this.mc];
    },
};
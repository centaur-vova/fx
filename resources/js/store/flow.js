import { COLORS, LABEL_COLORS } from '../modules/config.js';
import { getFlowDefaults } from './defaults.js';

export const flow = {
    mc: 2,
    mode: 'normal',
    scale: 1,
    hoverRand: false,
    hideMcSlider: false,
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
                class: btn.class || 'default',
                full_width: btn.full_width || false,
                semaphore_driver: btn.semaphore_driver || 'shared',
                tooltip: btn.tooltip || '',
            }));
        }
    },

    get mcColor() {
        return COLORS[this.mc];
    },

    get labelColor() {
        return LABEL_COLORS[this.mc];
    },
};
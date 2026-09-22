import { resetThemeColors } from "../modules/theme-config";
import { purgeTransientTasks, resetTaskColors } from "../modules/task-store";

export const theme = {
    isSwitching: false,

    switchTheme(themeName) {
        if (!window.THEMES?.includes(themeName)) return;

        const info = window.THEME_MAP?.[themeName];
        if (!info) return;

        this.isSwitching = true;

        // Update CSS
        const oldLink = document.querySelector('link[data-theme]');
        const newLink = document.createElement('link');
        newLink.rel = 'stylesheet';
        newLink.href = `/dist/themes/${info.cssFile}`;
        newLink.setAttribute('data-theme', '');
        newLink.onload = () => {
            this.isSwitching = false;
        };
        if (oldLink) oldLink.remove();
        document.head.appendChild(newLink);

        // Remove old config script
        const oldScript = document.querySelector('script[data-theme-config]');
        if (oldScript) oldScript.remove();

        // Load new config
        const newScript = document.createElement('script');
        newScript.src = `/dist/themes/${info.configFile}`;
        newScript.setAttribute('data-theme-config', '');
        newScript.onload = () => {
            this.initFlow();
            resetThemeColors();
            resetTaskColors();
            purgeTransientTasks();
        }
        newScript.onerror = () => location.reload();
        document.head.appendChild(newScript);

        // Update URL
        const url = new URL(window.location);
        url.searchParams.set('theme', themeName);
        history.pushState(null, '', url);
    },
};
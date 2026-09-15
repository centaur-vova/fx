export const toast = {
    toast: {
        show: false,
        success: true,
        content: '',
        timeout: null,
    },

    showToast(message, isSuccess = true, count = null) {
        if (this.toast.timeout) clearTimeout(this.toast.timeout);

        this.toast.success = isSuccess;
        if (isSuccess && count !== null) {
            this.toast.content = `<b class="toast-brand">${count}</b> ${message}`;
        } else {
            this.toast.content = (isSuccess ? '' : '<b class="toast-brand">ERROR:</b> ') + message;
        }
        this.toast.show = true;

        this.toast.timeout = setTimeout(() => {
            this.toast.show = false;
            this.toast.timeout = null;
        }, 2000);
    },
};

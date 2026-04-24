import { WS, TASK } from './config.js';

export const decodeMessage = (rawData, labels) => {
    // Binary
    if (rawData instanceof ArrayBuffer) {
        const view = new DataView(rawData);

        if (rawData.byteLength === 13) {
            const type = view.getUint8(0);
            if (type === WS.BINARY_TYPE.STATUS_UPDATE) {
                const status = TASK.STATUS_MAP[view.getUint8(1)];
                return {
                    event: WS.EVENT.STATUS_CHANGED,
                    data: {
                        status: status,
                        taskId: view.getBigUint64(2).toString(),
                        mc: view.getUint8(10),
                        progress: view.getUint8(11),
                        worker: view.getUint8(12) || null,
                        message: labels[status] || ''
                    }
                };
            }
        }

        const decoder = new TextDecoder();
        const text = decoder.decode(rawData);
        try {
            const { event, data } = JSON.parse(text);
            return { event, data };
        } catch (e) {
            console.warn("Binary JSON parse failed", e);
            return null;
        }
    }

    try {
        const { event, data } = JSON.parse(rawData);
        return { event, data };
    } catch (e) {
        console.error("WS Parse Error", e);
        return null;
    }
};

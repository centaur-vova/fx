import { WS, TASK } from './config.js';

export const decodeMessage = (rawData, labels) => {
    // Binary
    if (rawData instanceof ArrayBuffer) {
        const view = new DataView(rawData);
        const type = view.getUint8(0); // get magick byte

        // 9 bytes binary proto with magic byte
        if (rawData.byteLength === 9 && type === WS.BINARY_TYPE.STATUS_UPDATE) {
            const status = TASK.STATUS_MAP[view.getUint8(1)];

            const packed = view.getUint32(2);

            // Unpack task ID (31 bits) and semaphore type (1 bit) from a single uint32
            const taskId = packed & 0x7FFFFFFF,
                sem = (packed >>> 31) & 1;

            return {
                event: WS.EVENT.STATUS_CHANGED,
                data: {
                    status: status,
                    mc: view.getUint8(6),
                    progress: view.getUint8(7),
                    worker: view.getUint8(8),
                    message: labels[status] || '',
                    taskId,
                    sem,
                }
            };
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

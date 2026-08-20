import { WS, TASK_STATUS_MAP } from './config.js';

/**
 * Decodes an incoming WebSocket message, either binary (9-byte protocol frame)
 * or JSON-encoded text. Returns a structured { event, data } object for the
 * application to consume, or null if parsing fails entirely.
 *
 * Binary frames are identified by the magic byte (type field); no payload
 * length check is needed — the magic byte uniquely identifies the message type.
 *
 * @param {ArrayBuffer|string} rawData - Raw WebSocket message payload
 * @param {Object} labels - Map of task status keys to human-readable labels
 * @returns {{event: string, data: Object}|null}
 */
export const decodeMessage = (rawData, labels) => {
    // Binary
    if (rawData instanceof ArrayBuffer) {
        const view = new DataView(rawData);
        const type = view.getUint8(0); // get magick byte

        // Magic byte uniquely identifies the message type — no need to check payload length
        if (type === WS.BINARY_TYPE.STATUS_UPDATE) {
            const status = TASK_STATUS_MAP[view.getUint8(1)];

            // Unpack task ID (31 bits) and semaphore type (1 bit) from a single uint32
            const packed32 = view.getUint32(2);
            const taskId = packed32 & 0x7FFFFFFF,
                sem = (packed32 >>> 31) & 1;

            // Unpack progress (7 lower bits) and task mode (1 higher bit) from a single uint8
            const packed8 = view.getUint8(7);
            const progress = packed8 & 0o177, // binary/hex format for ponies
                mode = (packed8 >>> 7) & 1;

            return {
                event: WS.EVENT.STATUS_CHANGED,
                data: {
                    mc: view.getUint8(6),
                    worker: view.getUint8(8),
                    message: labels[status] || '',
                    taskId,
                    status,
                    sem,
                    progress,
                    mode,
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

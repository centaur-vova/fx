import ws from 'k6/experimental/websockets';
import { setInterval, clearInterval } from 'k6/timers';

export const options = {
    scenarios: {
        tasks_load: {
            executor: 'per-vu-iterations', // One VU = one stable connection
            vus: 10000,                     // Start with 10k connections
            iterations: 1,                 // Exactly ONE iteration per VU
            maxDuration: '45s',            // Safety margin for test lifetime
        },
    },
};

export default function () {
    const url = __ENV.WS_URL || 'ws://localhost:8080/ws';

    // Open exactly ONE socket per VU
    const socket = new ws.WebSocket(url);

    let intervalId;

    socket.addEventListener('open', () => {
        // Send initial ping once, avoid console spam
        socket.send('{"event":"ping"}');

        // Keep session alive with lightweight async interval
        intervalId = setInterval(() => {
            if (socket.readyState === ws.WebSocket.OPEN) {
                socket.send('{"event":"ping"}');
            }
        }, 10000); // Ping every 10s to avoid kernel buffer overload
    });

    socket.addEventListener('message', () => {
        // Silently consume incoming messages — no parsing, no CPU waste
    });

    socket.addEventListener('close', () => {
        if (intervalId) clearInterval(intervalId);
    });

    socket.addEventListener('error', (e) => {
        // Log only critical network failures
        console.error('WS Connection Error:', e.message);
    });
}
const MSG_CHART_ID = 'msg-chart';

//// MESSAGES CHART

let msgHistory = new Array(20).fill(0);
let msgChart = null;

function initMessagesChart() {
    const canvas = document.getElementById(MSG_CHART_ID);
    if (!canvas) return;
    msgChart = canvas.getContext('2d');
}

function updateMessagesChart(messages) {
    if (!msgChart) initMessagesChart();
    if (!msgChart) return;

    msgHistory.push(messages);
    if (msgHistory.length > 20) msgHistory.shift();

    const canvas = document.getElementById(MSG_CHART_ID);
    const rect = canvas.getBoundingClientRect();
    const w = canvas.width = rect.width;
    const h = canvas.height = rect.height;
    msgChart.clearRect(0, 0, w, h);
    msgChart.beginPath();
    msgChart.strokeStyle = '#10b981';
    msgChart.lineWidth = 1;

    const step = w / (msgHistory.length - 1);
    const max = Math.max(...msgHistory, 1);

    msgHistory.forEach((val, i) => {
        const x = i * step;
        const y = h - (val / max) * h;
        if (i === 0) msgChart.moveTo(x, y);
        else msgChart.lineTo(x, y);
    });
    msgChart.stroke();
}

export { updateMessagesChart }

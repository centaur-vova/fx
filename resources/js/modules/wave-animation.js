/**
 * WaveAnimation — controls sinusoidal motion for a task square.
 * Each instance holds its own random parameters for unique behavior.
 */
export class WaveAnimation {
    constructor(mc = 1) {
        // Logarithmic scaling: log(mc + 1) / log(256)
        // This gives high sensitivity at low mc, flattening at high mc
        const rawIntensity = Math.log(mc + 1) / Math.log(256); // 0..1
        const intensity = Math.min(rawIntensity, 1);

        this.amplitudeY = 0.005 + intensity * 0.055;
        this.amplitudeX = 0.0005 + intensity * 0.0035;
        this.frequencyY = 3 + intensity * 27;
        this.frequencyX = 3 + intensity * 27;
        this.phaseY = Math.random() * Math.PI * 2;
        this.phaseX = Math.random() * Math.PI * 2;
        this.baseY = null;
    }

    /**
     * Apply wave motion to a task.
     * @param {Object} task - The task object (must have id, y, currentX, targetX)
     * @param {number} time - Current time in seconds (Date.now() / 1000)
     */
    apply(task, time) {
        // Move toward target Y
        task.y += (task.targetY - task.y) * 0.1;

        // Store base Y for sinusoidal wobble
        const yBase = this.baseY ?? task.y;
        this.baseY = yBase;

        task.y = yBase + Math.sin(time * this.frequencyY + this.phaseY) * this.amplitudeY;

        // Move toward target X + sinusoidal wobble
        task.currentX += (task.targetX - task.currentX) * 0.1;
        task.currentX += Math.sin(time * this.frequencyX + this.phaseX) * this.amplitudeX;
    }
}
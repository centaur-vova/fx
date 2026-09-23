/**
 * WaveAnimation — controls sinusoidal motion for a task square.
 * Each instance holds its own random parameters for unique behavior.
 */
export class WaveAnimation {
    // Tuning knobs for wave motion
    static AMPLITUDE_Y_MAX = 0.015;
    static AMPLITUDE_X_MAX = 0.001;
    static FREQUENCY_MAX = 12;

    constructor(mc = 1) {
        const rawIntensity = Math.log(mc + 1) / Math.log(256);
        const intensity = Math.min(rawIntensity, 1);

        this.amplitudeY = 0.005 + intensity * WaveAnimation.AMPLITUDE_Y_MAX;
        this.amplitudeX = 0.0005 + intensity * WaveAnimation.AMPLITUDE_X_MAX;
        this.frequencyY = 3 + intensity * WaveAnimation.FREQUENCY_MAX;
        this.frequencyX = 3 + intensity * WaveAnimation.FREQUENCY_MAX;
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
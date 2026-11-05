const round = (n: number) => Math.round(n * 1000) / 1000;

/**
 * SVG path of the waveform of `peaks` (values in [-1, 1]) in a `N × 2` box,
 * N being the number of peaks: symmetric around the middle line, scaled so
 * that the loudest peak reaches the edges. Flat without any sound.
 */
export function waveformPath(peaks: number[]): string {
    const count = peaks.length;
    if (count === 0) {
        return 'M0 1L1 1Z';
    }
    const max = peaks.reduce((m, p) => Math.max(m, Math.abs(p)), 0);
    const amplitudes = peaks.map(p => (max > 0 ? Math.abs(p) / max : 0));

    const top = amplitudes.map((a, i) => `L${round(i + 0.5)} ${round(1 - a)}`);
    const bottom = amplitudes
        .map((a, i) => `L${round(i + 0.5)} ${round(1 + a)}`)
        .reverse();

    return `M0 1${top.join('')}L${count} 1${bottom.join('')}Z`;
}

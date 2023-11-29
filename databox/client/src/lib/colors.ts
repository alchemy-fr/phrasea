export function invertColor(hex: string, bw: boolean): string {
    if (hex.indexOf('#') === 0) {
        hex = hex.slice(1);
    }
    if (hex.length === 3) {
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }
    if (hex.length !== 6) {
        throw new Error('Invalid HEX color.');
    }
    const r: number = parseInt(hex.slice(0, 2), 16),
        g: number = parseInt(hex.slice(2, 4), 16),
        b: number = parseInt(hex.slice(4, 6), 16);
    if (bw) {
        return r * 0.299 + g * 0.587 + b * 0.114 > 186 ? '#000000' : '#FFFFFF';
    }
    const tr = (255 - r).toString(16);
    const tg = (255 - g).toString(16);
    const tb = (255 - b).toString(16);

    return '#' + padZero(tr) + padZero(tg) + padZero(tb);
}

function padZero(str: string, len: number = 2): string {
    const zeros = new Array(len).join('0');

    return (zeros + str).slice(-len);
}

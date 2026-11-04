export function formatNumber(value: number, lang?: string): string {
    return new Intl.NumberFormat(lang).format(value);
}

const binaryUnits = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];
const decimalUnits = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB'];

export function formatFileSize(
    size: number,
    binary = true,
    lang?: string
): string {
    const base = binary ? 1024 : 1000;
    const units = binary ? binaryUnits : decimalUnits;
    const unit =
        size > 0
            ? Math.min(
                  Math.floor(Math.log(size) / Math.log(base)),
                  units.length - 1
              )
            : 0;
    const v = Math.round((100 * size) / Math.pow(base, unit)) / 100;

    return `${formatNumber(v, lang)} ${units[unit]}`;
}

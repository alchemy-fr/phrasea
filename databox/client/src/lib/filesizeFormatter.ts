import {TFunction} from '@alchemy/i18n';

export function formatFilesize(
    t: TFunction,
    size: number,
    binary: boolean,
    locale: string
): string {
    const base = binary ? 1024 : 1000;
    const unit = size > 0 ? Math.floor(Math.log(size) / Math.log(base)) : 0;
    const v = Math.round(100 * (size / Math.pow(base, unit))) / 100.0;
    const value = v.toLocaleString(locale);

    if (binary) {
        switch (unit) {
            case 0:
                return t('attribute.type.size.unit.bytes', {
                    value,
                    defaultValue: '{{value}} Bytes',
                });
            case 1:
                return t('attribute.type.size.unit.kib', {
                    value,
                    defaultValue: '{{value}} KiB',
                });
            case 2:
                return t('attribute.type.size.unit.mib', {
                    value,
                    defaultValue: '{{value}} MiB',
                });
            case 3:
                return t('attribute.type.size.unit.gib', {
                    value,
                    defaultValue: '{{value}} GiB',
                });
            case 4:
                return t('attribute.type.size.unit.tib', {
                    value,
                    defaultValue: '{{value}} TiB',
                });
            case 5:
                return t('attribute.type.size.unit.pib', {
                    value,
                    defaultValue: '{{value}} PiB',
                });
            case 6:
                return t('attribute.type.size.unit.eib', {
                    value,
                    defaultValue: '{{value}} EiB',
                });
            case 7:
                return t('attribute.type.size.unit.zib', {
                    value,
                    defaultValue: '{{value}} ZiB',
                });
            default:
            case 8:
                return t('attribute.type.size.unit.yib', {
                    value,
                    defaultValue: '{{value}} YiB',
                });
        }
    } else {
        switch (unit) {
            case 0:
                return t('attribute.type.size.unit.bytes', {
                    value,
                    defaultValue: '{{value}} Bytes',
                });
            case 1:
                return t('attribute.type.size.unit.kb', {
                    value,
                    defaultValue: '{{value}} KB',
                });
            case 2:
                return t('attribute.type.size.unit.mb', {
                    value,
                    defaultValue: '{{value}} MB',
                });
            case 3:
                return t('attribute.type.size.unit.gb', {
                    value,
                    defaultValue: '{{value}} GB',
                });
            case 4:
                return t('attribute.type.size.unit.tb', {
                    value,
                    defaultValue: '{{value}} TB',
                });
            case 5:
                return t('attribute.type.size.unit.pb', {
                    value,
                    defaultValue: '{{value}} PB',
                });
            case 6:
                return t('attribute.type.size.unit.eb', {
                    value,
                    defaultValue: '{{value}} EB',
                });
            case 7:
                return t('attribute.type.size.unit.zb', {
                    value,
                    defaultValue: '{{value}} ZB',
                });
            default:
            case 8:
                return t('attribute.type.size.unit.yb', {
                    value,
                    defaultValue: '{{value}} YB',
                });
        }
    }
}

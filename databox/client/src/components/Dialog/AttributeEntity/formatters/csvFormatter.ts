import {AttributeEntity} from '../../../../types.ts';
import {Formatter, getLocalizedValue} from './formatterTypes.ts';

export const csvFormatter: Formatter = (
    list: AttributeEntity[],
    allLocales,
    locale
) => {
    const availableLocales: string[] = list.reduce(
        (acc: string[], item: AttributeEntity) => {
            const itemLocales = Object.keys(item.translations);
            itemLocales.forEach((lang: string) => {
                if (!acc.includes(lang)) {
                    acc.push(lang);
                }
            });
            return acc;
        },
        []
    );

    const headers = [
        'id',
        'value',
        ...(allLocales ? availableLocales : []),
    ].join(',');

    const rows = list
        .map(item => {
            return [
                item.id,
                allLocales ? item.value : getLocalizedValue(item, locale),
                ...(allLocales
                    ? availableLocales.map(
                          (lang: string) => item.translations[lang] || ''
                      )
                    : []),
            ].join(',');
        })
        .join('\n');

    return `${headers}\n${rows}`;
};

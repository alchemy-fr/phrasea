import {AttributeEntity} from '../../../../types.ts';
import {Formatter, getLocalizedValue} from './formatterTypes.ts';

export const jsonFormatter: Formatter = (
    list: AttributeEntity[],
    allLocales,
    locale
) => {
    return JSON.stringify(
        list.map(v => ({
            id: v.id,
            value: allLocales ? v.value : getLocalizedValue(v, locale),
            translations: allLocales ? v.translations : undefined,
        })),
        null,
        2
    );
};

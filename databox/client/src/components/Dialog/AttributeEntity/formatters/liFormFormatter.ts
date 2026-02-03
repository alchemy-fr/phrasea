import {AttributeEntity} from '../../../../types.ts';
import {Formatter, getLocalizedValue} from './formatterTypes.ts';

export const liFormFormatter: Formatter = (
    list: AttributeEntity[],
    allLocales,
    locale
) => {
    return JSON.stringify(
        {
            enum: list.map(e => e.id),
            enum_titles: list.map(e =>
                allLocales
                    ? getAllLocalesTitles(e)
                    : getLocalizedValue(e, locale)
            ),
        },
        null,
        2
    );
};

function getAllLocalesTitles(entity: AttributeEntity): string {
    const translations = entity.translations
        ? Object.entries(entity.translations)
        : [];

    if (translations.length === 0) {
        return entity.value;
    }

    return translations.map(t => t[1]).join(' / ');
}

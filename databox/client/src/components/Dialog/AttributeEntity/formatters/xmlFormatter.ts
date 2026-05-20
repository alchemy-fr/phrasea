import {AttributeEntity} from '../../../../types.ts';
import {Formatter, getLocalizedValue} from './formatterTypes.ts';

export const xmlFormatter: Formatter = (
    list: AttributeEntity[],
    allLocales,
    locale
) => {
    const xmlItems = list
        .map(item => {
            const id = item.id;
            const value = allLocales
                ? item.value
                : getLocalizedValue(item, locale);
            const translations = allLocales ? item.translations : undefined;

            const translationElements: string[] = translations
                ? Object.entries(translations).map(
                      ([lang, val]) =>
                          `<translation lang="${lang}">${val}</translation>`
                  )
                : [];

            return `
    <item id="${id}">
        <value>${value}</value>${
            item.emoji
                ? `
        <emoji>${item.emoji}</emoji>`
                : ''
        }${
            item.color
                ? `
        <color>${item.color}</color>`
                : ''
        }${
            translationElements.length > 0
                ? `
        <translations>
            ${translationElements.join(`
            `)}
        </translations>`
                : ''
        }
    </item>`;
        })
        .join('');

    return `<items>${xmlItems}
</items>`;
};

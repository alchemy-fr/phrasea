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

            let translationElements = '';
            if (translations) {
                translationElements = Object.entries(translations)
                    .map(
                        ([lang, val]) =>
                            `<translation lang="${lang}">${val}</translation>`
                    )
                    .join('');
            }

            return `
    <item id="${id}">
            <value>${value}</value>
            ${translationElements}
        </item>`;
        })
        .join('');

    return `<items>${xmlItems}
</items>`;
};

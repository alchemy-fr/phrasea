import {AttributeEntity} from '../../../../types.ts';

export type Formatter = (
    list: AttributeEntity[],
    allLocales: boolean,
    locale?: string
) => string;

export function getLocalizedValue(
    entity: AttributeEntity,
    locale?: string
): string {
    if (!locale) {
        return entity.value;
    }

    return entity.translations[locale] ?? entity.value;
}

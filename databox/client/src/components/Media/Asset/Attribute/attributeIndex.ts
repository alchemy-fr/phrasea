import {Attribute, AttributeDefinition} from "../../../../types.ts";
import {NO_LOCALE} from "./AttributesEditor.tsx";
import {getBestLocaleOfTranslations} from '@alchemy/i18n/src/Locale/localeHelper.ts'

type AttributeDefinitionIndex = {
    definition: AttributeDefinition;
    locales: {
        [locale: string]: Attribute | Attribute[];
    };
};

export type AttributeDefinitionIndices = AttributeDefinitionIndex[];

export type AttributeIndex = {
    [definitionId: string]: AttributeDefinitionIndex;
}

export type AttributeGroup = {
    definition: AttributeDefinition;
    attribute: Attribute | Attribute[];
}

export function buildAttributesIndex(attributes: Attribute[]): AttributeIndex {
    const index: AttributeIndex = {};

    attributes.forEach((attribute) => {
        const definition = attribute.definition;
        const definitionId = definition.id;

        index[definitionId] ??= {
            definition,
            locales: {},
        };
        const locale = attribute.locale ?? NO_LOCALE;
        const localesContainer = index[definitionId].locales;
        if (definition.multiple) {
            (localesContainer[locale] as Attribute[]) ??= [];
            (localesContainer[locale] as Attribute[]).push(attribute);
        } else {
            localesContainer[locale] = attribute;
        }
    });

    return index;
}

export function buildAttributesGroupedByDefinition(
    attributes: Attribute[]
): AttributeGroup[] {
    const index = buildAttributesIndex(attributes);
    const groups: AttributeGroup[] = [];

    Object.keys(index).map(k => {
        const {definition, locales} = index[k];

        const l = getBestLocaleOfTranslations(locales);
        const v = locales[l ?? NO_LOCALE] ?? locales[NO_LOCALE];

        if (v) {
            groups.push({
                definition,
                attribute: v,
            });
        }
    });

    return groups;
}

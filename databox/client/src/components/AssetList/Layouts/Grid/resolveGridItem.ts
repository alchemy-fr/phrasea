import {useContext, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {
    Asset,
    AttributeDefinitionOrBuiltIn,
    ProfileItem,
    ProfileItemType,
} from '../../../../types';
import {
    getBuiltInAttributeValueResolver,
    useIndexById,
} from '../../../../store/attributeDefinitionStore.ts';
import {buildAttributesGroupedByDefinition} from '../../../Media/Asset/Attribute/attributeIndex.ts';
import {getAttributeType} from '../../../Media/Asset/Attribute/types/getAttributeType.ts';
import {AttributeFormatContext} from '../../../Media/Asset/Attribute/Format/AttributeFormatContext';
import {BuiltInAttributeEnum} from '../../../Media/Search/search.ts';

export type ResolvedGridItem = {
    item: ProfileItem;
    definition: AttributeDefinitionOrBuiltIn;
    /** Formatted, human-readable value; empty string when no value. */
    value: string;
};

/**
 * Resolves the displayable value of grid ProfileItems for a given asset,
 * reusing the same value/format primitives as the List/preview attribute view.
 * Items with no value (and displayEmpty !== true) are dropped.
 */
export function useResolvedGridItems(
    asset: Asset,
    items: ProfileItem[]
): ResolvedGridItem[] {
    const {t, i18n} = useTranslation();
    const definitionsIndex = useIndexById(true);
    const formatContext = useContext(AttributeFormatContext);

    return useMemo<ResolvedGridItem[]>(() => {
        if (items.length === 0) {
            return [];
        }

        const attributeGroups = buildAttributesGroupedByDefinition(
            asset.attributes
        );

        const formatValue = (
            definition: AttributeDefinitionOrBuiltIn,
            value: unknown,
            override?: string,
            locale?: string
        ): string =>
            getAttributeType(definition.type).formatValueAsString({
                uiLocale: i18n.language,
                t,
                value,
                locale,
                format:
                    override ??
                    formatContext.getFormat(definition.type, definition.id),
            }) ?? '';

        const resolved: ResolvedGridItem[] = [];

        for (const item of items) {
            if (item.type === ProfileItemType.Definition) {
                const defId = item.definition!;
                const group = attributeGroups.find(
                    g => g.definition.id === defId
                );
                const definition = group?.definition ?? definitionsIndex[defId];
                if (!definition) {
                    continue;
                }

                const attribute = group?.attribute;
                const hasValue = definition.multiple
                    ? Array.isArray(attribute) && attribute.length > 0
                    : !!attribute;

                if (!hasValue && !item.displayEmpty) {
                    continue;
                }

                const value = hasValue
                    ? definition.multiple
                        ? (attribute as {value: unknown}[])
                              .map(a =>
                                  formatValue(definition, a.value, item.format)
                              )
                              .filter(Boolean)
                              .join(', ')
                        : formatValue(
                              definition,
                              (attribute as {value: unknown; locale?: string})
                                  .value,
                              item.format,
                              (attribute as {locale?: string}).locale
                          )
                    : '';

                resolved.push({item, definition, value});
            } else if (item.type === ProfileItemType.BuiltIn) {
                const getValueFromAsset = getBuiltInAttributeValueResolver(
                    item.key as BuiltInAttributeEnum
                );
                const definition = definitionsIndex[item.key!];
                if (!getValueFromAsset || !definition) {
                    continue;
                }

                const raw = getValueFromAsset(asset);
                const hasValue = definition.multiple
                    ? Array.isArray(raw) && raw.length > 0
                    : raw !== undefined && raw !== null && raw !== '';

                if (!hasValue && !item.displayEmpty) {
                    continue;
                }

                const value = hasValue
                    ? definition.multiple
                        ? (raw as unknown[])
                              .map(v => formatValue(definition, v, item.format))
                              .filter(Boolean)
                              .join(', ')
                        : formatValue(definition, raw, item.format)
                    : '';

                resolved.push({item, definition, value});
            }
            // Dividers/Spacers are not rendered on the grid card.
        }

        return resolved;
    }, [items, asset, definitionsIndex, formatContext, i18n.language, t]);
}

import {ReactNode, useContext, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {
    Asset,
    AttributeDefinitionOrBuiltIn,
    ProfileItem,
    ProfileItemType,
} from '../../../../types';
import {AttributeType} from '../../../../api/types.ts';
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
    /** Stringified value; used for the label prefix, tooltip and empty check. */
    value: string;
    /** Rich per-value ReactNodes (e.g. tag pills) from the type formatter. */
    nodes: ReactNode[];
    /** Raw per-value values (for boolean icon / entity emoji|color rendering). */
    raws: unknown[];
    /** When true, the value defaults to rich rendering (tags, entities, ...). */
    richCapable: boolean;
};

type RawValue = {value: unknown; locale?: string};

// Types whose formatter renders a non-textual ReactNode that must not be
// flattened into a plain Chip. Multi-valued attributes are always treated as
// rich too (see resolution below).
const RICH_TYPES = new Set<AttributeType>([
    AttributeType.Tag,
    AttributeType.AttributeEntity,
    AttributeType.User,
    AttributeType.Story,
    AttributeType.Color,
    AttributeType.GeoPoint,
    AttributeType.Html,
    AttributeType.Rendition,
    AttributeType.Privacy,
    AttributeType.FileType,
]);

/** Whether a value defaults to rich (ReactNode) rendering on the grid card. */
export function isRichCapable(def: AttributeDefinitionOrBuiltIn): boolean {
    return !!def.multiple || RICH_TYPES.has(def.type);
}

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

        const format = (
            definition: AttributeDefinitionOrBuiltIn,
            item: ProfileItem
        ) =>
            item.format ||
            formatContext.getFormat(definition.type, definition.id);

        const push = (
            resolved: ResolvedGridItem[],
            item: ProfileItem,
            definition: AttributeDefinitionOrBuiltIn,
            values: RawValue[]
        ) => {
            const at = getAttributeType(definition.type);
            const fmt = format(definition, item);
            const parts = values.map(v => ({
                str:
                    at.formatValueAsString({
                        uiLocale: i18n.language,
                        t,
                        value: v.value,
                        locale: v.locale,
                        format: fmt,
                    }) ?? '',
                node: at.formatValue({
                    uiLocale: i18n.language,
                    t,
                    value: v.value,
                    locale: v.locale,
                    format: fmt,
                }),
            }));

            resolved.push({
                item,
                definition,
                value: parts
                    .map(p => p.str)
                    .filter(Boolean)
                    .join(', '),
                nodes: parts
                    .map(p => p.node)
                    .filter(n => n !== undefined && n !== null),
                raws: values.map(v => v.value),
                richCapable: isRichCapable(definition),
            });
        };

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
                const values: RawValue[] = definition.multiple
                    ? Array.isArray(attribute)
                        ? (
                              attribute as {value: unknown; locale?: string}[]
                          ).map(a => ({value: a.value, locale: a.locale}))
                        : []
                    : attribute
                      ? [
                            {
                                value: (
                                    attribute as {
                                        value: unknown;
                                        locale?: string;
                                    }
                                ).value,
                                locale: (attribute as {locale?: string}).locale,
                            },
                        ]
                      : [];

                if (values.length === 0 && !item.displayEmpty) {
                    continue;
                }
                push(resolved, item, definition, values);
            } else if (item.type === ProfileItemType.BuiltIn) {
                const getValueFromAsset = getBuiltInAttributeValueResolver(
                    item.key as BuiltInAttributeEnum
                );
                const definition = definitionsIndex[item.key!];
                if (!getValueFromAsset || !definition) {
                    continue;
                }

                const raw = getValueFromAsset(asset);
                const values: RawValue[] = definition.multiple
                    ? Array.isArray(raw)
                        ? (raw as unknown[]).map(v => ({value: v}))
                        : []
                    : raw !== undefined && raw !== null && raw !== ''
                      ? [{value: raw}]
                      : [];

                if (values.length === 0 && !item.displayEmpty) {
                    continue;
                }
                push(resolved, item, definition, values);
            }
            // Dividers/Spacers are not rendered on the grid card.
        }

        return resolved;
    }, [items, asset, definitionsIndex, formatContext, i18n.language, t]);
}

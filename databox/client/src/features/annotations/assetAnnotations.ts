import type {Asset, AssetAnnotation, Attribute} from '@/types/api';
import {
    formatAttributeString,
    type FormatContext,
} from '@/features/attributes/types/registry';
import type {AnnotationTarget} from './AnnotationsProvider';
import {
    getAnnotationLabel,
    getAnnotationTypeLabel,
    isDefaultAnnotationName,
} from './annotationNames';

/**
 * The shapes of an attribute value, read only: each one is named after the
 * value ("Arthur Dupont", followed by its own name or text when it has one:
 * "Arthur Dupont · Profile"), else after itself, with the name of the
 * attribute as `title`.
 */
export function labelAttributeAnnotations(
    attribute: Attribute,
    ctx: FormatContext,
    format?: string
): AssetAnnotation[] {
    const {t} = ctx;
    const definition = attribute.definition;
    const value = (
        attribute.invalid
            ? String(attribute.value ?? '')
            : formatAttributeString(definition.type, attribute.value, format, {
                  ...ctx,
                  locale: attribute.locale,
              })
    ).trim();
    const title = definition.displayName ?? definition.name;

    return (attribute.assetAnnotations ?? []).map(shape => {
        const own = getAnnotationLabel(t, shape);
        const generic =
            !shape.text &&
            (own === getAnnotationTypeLabel(t, shape.type) ||
                isDefaultAnnotationName(t, shape));

        return {
            ...shape,
            name: value ? (generic ? value : `${value} · ${own}`) : own,
            title,
        };
    });
}

/**
 * The shapes of every attribute value of the asset as one read only target,
 * shown by the player while nothing else is: each shape is named after its
 * value (see `labelAttributeAnnotations()`).
 */
export function getAssetAnnotationsTarget(
    asset: Pick<Asset, 'id' | 'attributes'>,
    ctx: FormatContext,
    getFormat: (type: string, definitionId?: string) => string | undefined
): AnnotationTarget {
    const annotations: AssetAnnotation[] = [];
    for (const attribute of asset.attributes ?? []) {
        const shapes = attribute.assetAnnotations;
        if (!shapes?.length) {
            continue;
        }
        const {definition} = attribute;
        labelAttributeAnnotations(
            attribute,
            ctx,
            getFormat(definition.type, definition.id)
        ).forEach((shape, i) => {
            annotations.push({
                ...shape,
                id: `${attribute.id}-${shape.id ?? i}`,
            });
        });
    }

    return {
        key: `asset-${asset.id}`,
        annotations,
        label: ctx.t('annotation.asset_label', 'Attributes'),
    };
}

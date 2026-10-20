import type {TFunction} from 'i18next';
import type {Asset, AssetAnnotation} from '@/types/api';
import type {AnnotationTarget} from './AnnotationsProvider';
import {getAnnotationLabel} from './annotationNames';

/**
 * The shapes of every attribute value of the asset as one read only target,
 * shown by the player while nothing else is: each shape is named after its
 * attribute.
 */
export function getAssetAnnotationsTarget(
    asset: Pick<Asset, 'id' | 'attributes'>,
    t: TFunction
): AnnotationTarget {
    const annotations: AssetAnnotation[] = [];
    for (const attribute of asset.attributes ?? []) {
        const shapes = attribute.assetAnnotations;
        if (!shapes?.length) {
            continue;
        }
        const name =
            attribute.definition.displayName ?? attribute.definition.name;
        shapes.forEach((shape, i) => {
            annotations.push({
                ...shape,
                id: `${attribute.id}-${shape.id ?? i}`,
                name: `${name}: ${getAnnotationLabel(t, shape)}`,
            });
        });
    }

    return {
        key: `asset-${asset.id}`,
        annotations,
        label: t('annotation.asset_label', 'Attributes'),
    };
}

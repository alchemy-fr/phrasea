'use client';

import {useQuery} from '@tanstack/react-query';
import type {
    Asset,
    AssetAnnotation,
    Attribute,
    FaceBox,
    WorkspaceIntegration,
} from '@/types/api';
import {
    getIntegrationsOfContext,
    IntegrationContext,
} from '@/lib/api/integrations';

/** Integration key of the face recognition (`FaceRecognitionIntegration`) */
export const faceRecognition = 'core.face_recognition';

export const faceRecognitionSetupsQueryKey = ['face-recognition-setups'];

/** Where a workspace keeps its persons and its faces */
export type FaceRecognitionSetup = {
    integration: WorkspaceIntegration;
    workspaceId: string;
    /** Entity list of the persons */
    entityList: string;
    /** Slug of the entity attribute holding the faces */
    attribute: string;
    /** ID of that attribute definition */
    attributeDefinition?: string;
};

export function setupOf(
    integration: WorkspaceIntegration
): FaceRecognitionSetup | undefined {
    const config = integration.config as
        | {
              entityList?: string;
              attribute?: string;
              attributeDefinition?: string;
          }
        | undefined;
    const workspaceId =
        typeof integration.workspace === 'string'
            ? integration.workspace.split('/').pop()
            : integration.workspace?.id;
    if (
        integration.integration !== faceRecognition ||
        !workspaceId ||
        !config?.entityList ||
        !config?.attribute
    ) {
        return undefined;
    }

    return {
        integration,
        workspaceId,
        entityList: config.entityList,
        attribute: config.attribute,
        attributeDefinition: config.attributeDefinition,
    };
}

/** The face recognition setups of the workspaces the user may use */
export function useFaceRecognitionSetups(enabled = true) {
    return useQuery({
        queryKey: faceRecognitionSetupsQueryKey,
        queryFn: async () => {
            const page = await getIntegrationsOfContext(
                IntegrationContext.AssetView
            );

            return page.items
                .filter(
                    i => i.enabled !== false && i.capabilities?.use !== false
                )
                .map(setupOf)
                .filter((s): s is FaceRecognitionSetup => !!s);
        },
        enabled,
        staleTime: 60_000,
    });
}

/** The rectangle annotation of a face value */
export function boxOf(attribute: Attribute): FaceBox | undefined {
    const rect = (attribute.assetAnnotations ?? []).find(
        (a: AssetAnnotation) => a.type === 'rect'
    );
    if (!rect) {
        return undefined;
    }

    return {
        x: Number(rect.x),
        y: Number(rect.y),
        w: Number(rect.w),
        h: Number(rect.h),
    };
}

/** The asset ID of a value listed by definition (an IRI or an object) */
export function assetIdOf(attribute: Attribute): string | undefined {
    const asset = attribute.asset as Asset | string | undefined;
    if (!asset) {
        return undefined;
    }

    return typeof asset === 'string' ? asset.split('/').pop() : asset.id;
}

'use client';

import type {
    Asset,
    AssetAnnotation,
    Attribute,
    FaceBox,
    WorkspaceIntegration,
} from '@/types/api';
import {AnnotationType} from '@/features/annotations/types';

/** Integration key of the face recognition (`FaceRecognitionIntegration`) */
export const faceRecognition = 'core.face_recognition';

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

/** The face annotation of a face value */
export function boxOf(attribute: Attribute): FaceBox | undefined {
    const face = (attribute.assetAnnotations ?? []).find(
        (a: AssetAnnotation) => a.type === AnnotationType.Face
    );
    if (!face) {
        return undefined;
    }

    return {
        x: Number(face.x),
        y: Number(face.y),
        w: Number(face.w),
        h: Number(face.h),
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

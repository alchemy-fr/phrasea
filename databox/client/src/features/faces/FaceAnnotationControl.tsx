'use client';

import {useCallback, useMemo, type ReactNode} from 'react';
import type {ApiFile, Asset, AssetAnnotation} from '@/types/api';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useFileIntegrations} from '@/features/integrations/FileIntegrations';
import {AnnotationType} from '@/features/annotations/types';
import type {FaceCardFace} from './FaceCard';
import {FacePersonPicker, useRun} from './FacePersonPicker';
import {useFaceActions} from './useFaceActions';
import {canNameFaces, facesOfAsset, setupOf} from './useFaceRecognition';

/** The person of a face, picked from the floating panel of its shape */
function FaceAnnotationControl({
    face,
    listId,
}: {
    face: FaceCardFace;
    listId: string;
}) {
    const [saving, run] = useRun();
    const {assign, name} = useFaceActions(face.asset.id);

    return (
        <div className="w-56" data-testid="face-annotation-person">
            <FacePersonPicker
                face={face}
                listId={listId}
                saving={saving}
                run={run}
                onAssign={assign}
                onName={name}
                className="h-7 min-h-7 px-2 text-xs"
            />
        </div>
    );
}

/**
 * The `renderControl` of the shapes of the asset: the face of the face
 * recognition gets its person picker, when the user may set it.
 */
export function useFaceAnnotationControl(
    asset: Asset | undefined,
    file: ApiFile | undefined
): ((annotation: AssetAnnotation) => ReactNode) | undefined {
    const {isAuthenticated} = useAuth();
    const integrations = useFileIntegrations(asset, file, isAuthenticated);
    const integration = integrations.find(
        i => i.capabilities?.use !== false && setupOf(i)
    );
    const setup = integration ? setupOf(integration) : undefined;
    const editable = !!(
        asset &&
        integration &&
        canNameFaces(integration, asset)
    );

    const attribute = setup?.attribute;
    const listId = setup?.entityList;
    const faces = useMemo(
        () =>
            asset && attribute && editable
                ? facesOfAsset(asset, attribute)
                : [],
        [asset, attribute, editable]
    );

    const render = useCallback(
        (annotation: AssetAnnotation) => {
            const face =
                annotation.type === AnnotationType.Face
                    ? faces.find(f => f.id === annotation.source?.attributeId)
                    : undefined;

            return face && listId ? (
                <FaceAnnotationControl face={face} listId={listId} />
            ) : null;
        },
        [faces, listId]
    );

    return faces.length > 0 ? render : undefined;
}

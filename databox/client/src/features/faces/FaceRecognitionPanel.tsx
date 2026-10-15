'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {RefreshCwIcon, ScanFaceIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {ApiFile, Asset, WorkspaceIntegration} from '@/types/api';
import {runIntegrationAction} from '@/lib/api/integrations';
import {deleteAssetAttribute, patchAssetAttribute} from '@/lib/api/assets';
import {Button} from '@/components/ui/button';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {FaceCard, type FaceCardFace} from './FaceCard';
import {boxOf, faceRecognition, setupOf} from './useFaceRecognition';

export {faceRecognition};

/** The faces of the asset: the values of its face attribute */
export function facesOfAsset(
    asset: Asset,
    attributeSlug: string
): FaceCardFace[] {
    return (asset.attributes ?? [])
        .filter(a => a.definition?.slug === attributeSlug)
        .flatMap(a => {
            const box = boxOf(a);

            return box
                ? [
                      {
                          id: a.id,
                          asset,
                          box,
                          origin: a.origin,
                          confidence: a.confidence,
                          entity: a.value ?? null,
                      },
                  ]
                : [];
        });
}

/**
 * Face recognition panel of the asset view: the faces detected on the asset
 * (the values of the face attribute, drawn as annotations), to name in place,
 * and the detection to run on demand.
 */
export function FaceRecognitionPanel({
    integration,
    asset,
}: {
    integration: WorkspaceIntegration;
    asset: Asset;
    file: ApiFile;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [detecting, setDetecting] = useState(false);
    const setup = setupOf(integration);
    const assetQueryKey = ['asset-view', asset.id];
    const refresh = () =>
        queryClient.invalidateQueries({queryKey: assetQueryKey});

    // The analyzer announces every detection of the asset
    useChannelEvent(
        `asset-${asset.id}`,
        `integration:${faceRecognition}`,
        () => void refresh()
    );

    const faces = useMemo(
        () => (setup ? facesOfAsset(asset, setup.attribute) : []),
        [asset, setup]
    );
    const canInteract =
        integration.capabilities?.interact !== false &&
        asset.capabilities?.edit !== false;

    const detect = async () => {
        setDetecting(true);
        try {
            await runIntegrationAction(integration.id, 'analyze', {
                assetId: asset.id,
            });
            toast.success(t('faces.detect_started', 'Face detection started'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setDetecting(false);
        }
    };

    const assign = async (face: FaceCardFace, entityId: string) => {
        try {
            const updated = await patchAssetAttribute(face.id, {
                value: entityId,
            });
            await refresh();
            toast.success(
                t('faces.named_toast', {
                    defaultValue: 'Face named {{name}}',
                    name: updated.value?.value ?? '',
                })
            );
        } catch (e: any) {
            toast.error(
                e?.message ??
                    t('faces.name_error', 'The face could not be named')
            );
            throw e;
        }
    };

    const remove = async (face: FaceCardFace) => {
        try {
            await deleteAssetAttribute(face.id);
            await refresh();
            toast.success(t('faces.forgotten', 'Face forgotten'));
        } catch (e: any) {
            toast.error(e?.message);
            throw e;
        }
    };

    return (
        <div className="space-y-2 text-sm" data-testid="face-recognition-panel">
            <div className="flex flex-wrap gap-2">
                {canInteract ? (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={detect}
                        loading={detecting}
                        data-testid="faces-detect"
                    >
                        <ScanFaceIcon /> {t('faces.detect', 'Detect faces')}
                    </Button>
                ) : null}
                <Button size="sm" variant="ghost" onClick={() => refresh()}>
                    <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                </Button>
            </div>
            {!setup ? (
                <p className="text-xs text-muted-foreground">
                    {t(
                        'faces.not_configured',
                        'The integration needs an entity list and an entity attribute to store the persons.'
                    )}
                </p>
            ) : faces.length > 0 ? (
                <div className="grid grid-cols-2 gap-2">
                    {faces.map(face => (
                        <FaceCard
                            key={face.id}
                            face={face}
                            listId={setup.entityList}
                            canEdit={canInteract}
                            onAssign={assign}
                            onRemove={remove}
                            cropSize={120}
                            showAssetLink={false}
                        />
                    ))}
                </div>
            ) : (
                <p className="text-xs text-muted-foreground">
                    {t('faces.none_detected', 'No face detected')}
                </p>
            )}
        </div>
    );
}

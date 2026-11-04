'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {RefreshCwIcon, ScanFaceIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {ApiFile, Asset, WorkspaceIntegration} from '@/types/api';
import {runIntegrationAction} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {FaceCard} from './FaceCard';
import {
    canNameFaces,
    faceRecognition,
    facesOfAsset,
    setupOf,
} from './useFaceRecognition';
import {useFaceActions} from './useFaceActions';

export {faceRecognition};

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
    const [detecting, setDetecting] = useState(false);
    const setup = setupOf(integration);
    const {refresh, assign, name, remove} = useFaceActions(asset.id);

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
    const canInteract = canNameFaces(integration, asset);

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
                            onName={name}
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

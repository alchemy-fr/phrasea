'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {RefreshCwIcon, ScanFaceIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {
    ApiFile,
    Asset,
    FaceBox,
    FaceIdentityOrigin,
    IntegrationData,
    WorkspaceIntegration,
} from '@/types/api';
import {
    getIntegrationData,
    IntegrationObjectType,
    runIntegrationAction,
} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {InlineLoader} from '@/components/ui/loader';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {FaceCard} from './FaceCard';

/** Integration key of the face recognition (`FaceRecognitionIntegration`) */
export const faceRecognition = 'core.face_recognition';

/** Name of the integration data holding the faces of an asset */
const facesDataName = 'faces';

/** One face of the `faces` integration data (`FaceRecognitionAnalyzer::buildSummary`) */
type FaceSummary = {
    id: string;
    box: FaceBox;
    confidence: number;
    identity: string | null;
    identityConfidence: number | null;
    identityOrigin: FaceIdentityOrigin | null;
    age?: number | null;
    gender?: string | null;
};

type FacesSummary = {faces: FaceSummary[]};

/** The stored value is the JSON summary; defensive against anything else */
function parseFaces(items: IntegrationData[]): FaceSummary[] | undefined {
    const data = items.find(d => d.name === facesDataName);
    if (!data) {
        return undefined;
    }
    try {
        const value =
            typeof data.value === 'string'
                ? (JSON.parse(data.value) as FacesSummary)
                : (data.value as FacesSummary);

        return Array.isArray(value?.faces) ? value.faces : [];
    } catch {
        return [];
    }
}

/**
 * Face recognition panel of the asset view: the faces detected on the asset
 * (the integration works per asset, on its configured rendition, whatever
 * file is displayed), to name in place, and the detection to run on demand.
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
    const queryKey = ['integration-data', integration.id, 'asset', asset.id];

    const data = useQuery({
        queryKey,
        queryFn: ({signal}) =>
            getIntegrationData(
                integration.id,
                {type: IntegrationObjectType.Asset, id: asset.id},
                signal
            ),
    });
    const refresh = () => queryClient.invalidateQueries({queryKey});

    // The analyzer announces every change of the faces of the asset
    // (detection, naming, propagation from another asset)
    useChannelEvent(
        `asset-${asset.id}`,
        `integration:${faceRecognition}`,
        () => void refresh()
    );

    const faces = useMemo(
        () => parseFaces(data.data?.items ?? []),
        [data.data]
    );
    const knownNames = useMemo(
        () =>
            Array.from(
                new Set(
                    (faces ?? [])
                        .map(f => f.identity)
                        .filter((n): n is string => !!n)
                )
            ),
        [faces]
    );
    const canInteract = integration.capabilities?.interact !== false;

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

    const identify = async (face: FaceSummary, identity: string | null) => {
        try {
            const summary: FacesSummary = await runIntegrationAction(
                integration.id,
                'identify',
                {faceId: face.id, identity}
            );
            // The action answers the new summary of the asset: shown right
            // away, the realtime event confirms it
            queryClient.setQueryData(
                queryKey,
                (previous: {items: IntegrationData[]} | undefined) =>
                    previous && {
                        ...previous,
                        items: previous.items.map(d =>
                            d.name === facesDataName
                                ? {...d, value: {faces: summary.faces}}
                                : d
                        ),
                    }
            );
            toast.success(
                identity
                    ? t('faces.named_toast', {
                          defaultValue: 'Face named {{name}}',
                          name: identity,
                      })
                    : t('faces.name_removed', 'Name removed')
            );
        } catch (e: any) {
            toast.error(
                e?.message ??
                    t('faces.name_error', 'The face could not be named')
            );
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
            {data.isLoading ? <InlineLoader /> : null}
            {faces && faces.length > 0 ? (
                <div className="grid grid-cols-2 gap-2">
                    {faces.map(face => (
                        <FaceCard
                            key={face.id}
                            face={{...face, asset}}
                            knownNames={knownNames}
                            onIdentify={canInteract ? identify : readOnly}
                            cropSize={120}
                            showAssetLink={false}
                        />
                    ))}
                </div>
            ) : !data.isLoading ? (
                <p className="text-xs text-muted-foreground">
                    {faces
                        ? t('faces.none_detected', 'No face detected')
                        : t(
                              'faces.not_analyzed',
                              'Faces have not been detected on this asset yet'
                          )}
                </p>
            ) : null}
        </div>
    );
}

const readOnly = async () => undefined;

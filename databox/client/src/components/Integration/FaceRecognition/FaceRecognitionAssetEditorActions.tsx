import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {
    Button,
    CircularProgress,
    IconButton,
    List,
    ListItem,
    ListItemButton,
    ListItemText,
    ListSubheader,
    TextField,
    Tooltip,
} from '@mui/material';
import FaceIcon from '@mui/icons-material/Face';
import EditIcon from '@mui/icons-material/Edit';
import CheckIcon from '@mui/icons-material/Check';
import CloseIcon from '@mui/icons-material/Close';
import PersonOffIcon from '@mui/icons-material/PersonOff';
import {useTranslation} from 'react-i18next';
import {
    getIntegrationsOfContext,
    IntegrationContext,
    ObjectType,
    runIntegrationAction,
} from '../../../api/integrations';
import IntegrationPanelContent from '../Common/IntegrationPanelContent';
import {AssetIntegrationActionsProps, Integration} from '../types.ts';
import {useChannelRegistration} from '../../../lib/pusher.ts';
import {
    AnnotationType,
    RectangleAnnotation,
} from '../../Media/Asset/Annotations/annotationTypes.ts';
import {DetectedFace, FaceIdentityOrigin, FacesData} from './types';
import ValueConfidence from '../AwsRekognition/ValueConfidence';

type Props = {} & AssetIntegrationActionsProps;

const FACES_DATA_NAME = 'faces';

const knownColor = 'green';
const unknownColor = 'red';

export default function FaceRecognitionAssetEditorActions({
    asset,
    integration,
    assetAnnotationsRef,
    enableInc,
}: Props) {
    const {t} = useTranslation();
    const [running, setRunning] = useState(false);
    const [saving, setSaving] = useState(false);
    const [facesData, setFacesData] = useState<FacesData | undefined>();
    const [loaded, setLoaded] = useState(false);
    const [editing, setEditing] = useState<string | undefined>();
    const [identityInput, setIdentityInput] = useState('');

    const loadFaces = useCallback(async () => {
        const r = await getIntegrationsOfContext(
            IntegrationContext.AssetView,
            asset.workspace.id,
            {
                objectType: ObjectType.Asset,
                objectId: asset.id,
            }
        );
        const data = r.result
            .find(i => i.id === integration.id)
            ?.data.find(d => d.name === FACES_DATA_NAME);

        setFacesData(data ? (JSON.parse(data.value) as FacesData) : undefined);
        setLoaded(true);
    }, [asset.id, asset.workspace.id, integration.id]);

    useEffect(() => {
        loadFaces();
    }, [loadFaces]);

    useChannelRegistration(
        `asset-${asset.id}`,
        `integration:${Integration.FaceRecognition}`,
        () => {
            setRunning(false);
            loadFaces();
        }
    );

    const process = async () => {
        setRunning(true);
        try {
            await runIntegrationAction('analyze', integration.id, {
                assetId: asset.id,
            });
        } catch (e) {
            setRunning(false);
            throw e;
        }
    };

    const identify = async (faceId: string, identity: string) => {
        setSaving(true);
        try {
            const result = (await runIntegrationAction(
                'identify',
                integration.id,
                {
                    faceId,
                    identity,
                }
            )) as FacesData;
            setFacesData({faces: result.faces});
            setEditing(undefined);
        } finally {
            setSaving(false);
        }
    };

    const faceTitle = useCallback(
        (face: DetectedFace, index: number): string =>
            face.identity ??
            t('face_recognition.face_n', {
                defaultValue: `Face #{{n}}`,
                n: index + 1,
            }),
        [t]
    );

    const annotations = useMemo<RectangleAnnotation[]>(() => {
        if (!enableInc || !facesData) {
            return [];
        }

        return facesData.faces.map((face, index) => ({
            id: `face-${face.id}`,
            type: AnnotationType.Rect,
            c: face.identity ? knownColor : unknownColor,
            x: face.box.x,
            y: face.box.y,
            w: face.box.w,
            h: face.box.h,
            name: faceTitle(face, index),
        }));
    }, [enableInc, facesData, faceTitle]);

    useEffect(() => {
        assetAnnotationsRef?.current?.replaceAnnotations(annotations);
    }, [enableInc, annotations, assetAnnotationsRef]);

    const selectFace = useCallback(
        (face: DetectedFace) => {
            const ar = assetAnnotationsRef?.current;
            const annotation = annotations.find(
                a => a.id === `face-${face.id}`
            );
            if (ar && annotation) {
                ar.replaceAnnotations(annotations);
                ar.selectAnnotation(annotation);
            }
        },
        [assetAnnotationsRef, annotations]
    );

    const startEditing = (face: DetectedFace) => {
        setIdentityInput(face.identity ?? '');
        setEditing(face.id);
    };

    const canInteract = !!integration.capabilities.interact;
    const faces = facesData?.faces ?? [];

    return (
        <>
            <IntegrationPanelContent>
                <Button
                    onClick={process}
                    disabled={running || !loaded || !canInteract}
                    variant={'contained'}
                    startIcon={
                        running ? (
                            <CircularProgress size={16} color={'inherit'} />
                        ) : (
                            <FaceIcon />
                        )
                    }
                >
                    {facesData
                        ? t(
                              'face_recognition.actions.detect_again',
                              `Detect faces again`
                          )
                        : t('face_recognition.actions.detect', `Detect faces`)}
                </Button>
            </IntegrationPanelContent>

            {facesData && (
                <List component="div" disablePadding>
                    <ListSubheader>
                        {t('face_recognition.faces', `Faces`)}
                    </ListSubheader>
                    {faces.length === 0 && (
                        <ListItem>
                            <ListItemText>
                                {t(
                                    'face_recognition.no_face_detected',
                                    `No face detected`
                                )}
                            </ListItemText>
                        </ListItem>
                    )}
                    {faces.map((face, index) => {
                        if (editing === face.id) {
                            return (
                                <ListItem key={face.id}>
                                    <TextField
                                        autoFocus
                                        size={'small'}
                                        fullWidth
                                        disabled={saving}
                                        value={identityInput}
                                        onChange={e =>
                                            setIdentityInput(e.target.value)
                                        }
                                        onKeyDown={e => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                identify(
                                                    face.id,
                                                    identityInput
                                                );
                                            } else if (e.key === 'Escape') {
                                                setEditing(undefined);
                                            }
                                        }}
                                        placeholder={t(
                                            'face_recognition.identity_placeholder',
                                            `Person name`
                                        )}
                                    />
                                    <IconButton
                                        disabled={saving}
                                        onClick={() =>
                                            identify(face.id, identityInput)
                                        }
                                        aria-label={t(
                                            'face_recognition.actions.save',
                                            `Save`
                                        )}
                                    >
                                        <CheckIcon />
                                    </IconButton>
                                    <IconButton
                                        disabled={saving}
                                        onClick={() => setEditing(undefined)}
                                        aria-label={t(
                                            'face_recognition.actions.cancel',
                                            `Cancel`
                                        )}
                                    >
                                        <CloseIcon />
                                    </IconButton>
                                </ListItem>
                            );
                        }

                        return (
                            <ListItemButton
                                key={face.id}
                                onClick={() => selectFace(face)}
                            >
                                <ListItemText
                                    primary={
                                        <>
                                            {faceTitle(face, index)}{' '}
                                            <small>
                                                (
                                                <ValueConfidence
                                                    confidence={
                                                        face.confidence * 100
                                                    }
                                                />
                                                )
                                            </small>
                                        </>
                                    }
                                    secondary={
                                        <FaceSecondary
                                            face={face}
                                            title={faceTitle(face, index)}
                                        />
                                    }
                                />
                                <Tooltip
                                    title={t(
                                        'face_recognition.actions.identify',
                                        `Identify this person`
                                    )}
                                >
                                    <span>
                                        <IconButton
                                            edge="end"
                                            disabled={!canInteract || saving}
                                            onClick={e => {
                                                e.stopPropagation();
                                                startEditing(face);
                                            }}
                                        >
                                            <EditIcon />
                                        </IconButton>
                                    </span>
                                </Tooltip>
                                {face.identity && (
                                    <Tooltip
                                        title={t(
                                            'face_recognition.actions.forget',
                                            `Forget this identity`
                                        )}
                                    >
                                        <span>
                                            <IconButton
                                                edge="end"
                                                disabled={
                                                    !canInteract || saving
                                                }
                                                onClick={e => {
                                                    e.stopPropagation();
                                                    identify(face.id, '');
                                                }}
                                            >
                                                <PersonOffIcon />
                                            </IconButton>
                                        </span>
                                    </Tooltip>
                                )}
                            </ListItemButton>
                        );
                    })}
                </List>
            )}
        </>
    );
}

function FaceSecondary({face, title}: {face: DetectedFace; title: string}) {
    const {t} = useTranslation();

    const parts: React.ReactNode[] = [];

    if (face.identity) {
        if (face.identityOrigin === FaceIdentityOrigin.Auto) {
            parts.push(
                t('face_recognition.auto_identified', {
                    defaultValue: `Recognized ({{confidence}}% similar)`,
                    confidence: Math.round(
                        (face.identityConfidence ?? 0) * 100
                    ),
                })
            );
        } else {
            parts.push(
                t('face_recognition.user_identified', `Identified by a user`)
            );
        }
    } else {
        parts.push(t('face_recognition.unknown', `Unknown person`));
    }

    const details: string[] = [];
    if (face.gender) {
        details.push(
            face.gender === 'F'
                ? t('face_recognition.gender.female', `Female`)
                : t('face_recognition.gender.male', `Male`)
        );
    }
    if (typeof face.age === 'number') {
        details.push(
            t('face_recognition.age', {
                defaultValue: `~{{age}} years old`,
                age: face.age,
            })
        );
    }
    if (details.length > 0) {
        parts.push(details.join(', '));
    }

    return <span title={title}>{parts.join(' · ')}</span>;
}

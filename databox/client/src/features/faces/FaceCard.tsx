'use client';

import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {CheckIcon, ExternalLinkIcon, Trash2Icon} from 'lucide-react';
import type {
    Asset,
    AttributeEntity,
    AttributeOrigin,
    FaceBox,
} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {Badge} from '@alchemy/next-ui/ui/misc';
import {Tooltip} from '@alchemy/next-ui/ui/overlays';
import {routes} from '@/lib/routes';
import {cn} from '@alchemy/next-ui/utils/cn';
import {FaceCrop} from './FaceCrop';
import {FacePersonPicker, isUnknownPerson, useRun} from './FacePersonPicker';

/** The person of a face, as the attribute value carries it */
export type FaceEntity = {
    id: string;
    /** Null while the person is pending (nobody named it) */
    value: string | null;
    note?: string | null;
    emoji?: string;
    color?: string;
    status?: number;
    translations?: Record<string, string>;
};

/** A face: a value of the face attribute of an asset */
export type FaceCardFace = {
    /** The attribute ID */
    id: string;
    asset: Asset;
    box: FaceBox;
    origin: AttributeOrigin;
    confidence?: number;
    entity?: FaceEntity | null;
};

/**
 * One detected face: its crop, the asset it comes from and its person, to set
 * (an existing person of the list, or a name for its unknown person), confirm (a recognized face
 * becomes a reference once a user confirms it) or forget.
 */
export function FaceCard<F extends FaceCardFace>({
    face,
    listId,
    canEdit,
    onAssign,
    onName,
    onRemove,
    cropSize,
    showAssetLink = true,
}: {
    face: F;
    /** Entity list of the persons */
    listId: string | undefined;
    canEdit: boolean;
    /** Gives the face to a person (the entity ID, and its name when known) */
    onAssign: (face: F, entityId: string, name?: string) => Promise<void>;
    /**
     * Names the unknown person of the face (renamed and approved), and so
     * every face of that person. Without it, a new person is created.
     */
    onName?: (face: F, name: string) => Promise<AttributeEntity>;
    onRemove: (face: F) => Promise<void>;
    cropSize?: number;
    /** Off where the asset is already displayed (its own view) */
    showAssetLink?: boolean;
}) {
    const {t} = useTranslation();
    const [saving, run] = useRun();
    const unknown = isUnknownPerson(face.entity);
    const recognized = !unknown && face.origin !== 'human';
    const asset = face.asset;

    return (
        <div
            className={cn(
                'flex flex-col gap-2 rounded-lg border bg-card p-2',
                unknown && 'border-dashed'
            )}
            data-testid="face-card"
        >
            <FaceCrop
                asset={asset}
                box={face.box}
                size={cropSize}
                className="w-full rounded"
            />

            <div className="flex items-center gap-1 text-xs">
                {unknown ? (
                    <Badge variant="muted">
                        {t('faces.unnamed', 'Unnamed')}
                    </Badge>
                ) : recognized ? (
                    <Tooltip
                        content={t(
                            'faces.recognized_help',
                            'Recognized automatically from a face named by a user. Confirm it to make it a reference.'
                        )}
                    >
                        <Badge variant="warning">
                            {t('faces.recognized', 'Recognized')}
                            {face.confidence != null
                                ? ` · ${Math.round(face.confidence * 100)}%`
                                : null}
                        </Badge>
                    </Tooltip>
                ) : (
                    <Badge variant="success">{t('faces.named', 'Named')}</Badge>
                )}
                <span className="flex-1" />
                {showAssetLink ? (
                    <Button size="icon-xs" variant="ghost" asChild>
                        <Link
                            href={routes.assetView(asset.id)}
                            title={
                                asset.name ?? asset.source?.fileName ?? asset.id
                            }
                            aria-label={t('faces.open_asset', 'Open asset')}
                        >
                            <ExternalLinkIcon />
                        </Link>
                    </Button>
                ) : null}
            </div>

            <div className="flex items-center gap-1" data-testid="face-person">
                <div className="min-w-0 flex-1">
                    <FacePersonPicker
                        face={face}
                        listId={listId}
                        disabled={!canEdit}
                        saving={saving}
                        run={run}
                        onAssign={onAssign}
                        onName={onName}
                    />
                </div>
                {recognized && canEdit ? (
                    <Tooltip content={t('faces.confirm', 'Confirm the person')}>
                        <Button
                            size="icon-xs"
                            variant="outline"
                            loading={saving}
                            onClick={() =>
                                void run(() =>
                                    onAssign(
                                        face,
                                        face.entity!.id,
                                        face.entity!.value ?? undefined
                                    )
                                )
                            }
                            data-testid="face-confirm"
                        >
                            <CheckIcon />
                        </Button>
                    </Tooltip>
                ) : null}
                {canEdit ? (
                    <Tooltip content={t('faces.forget', 'Forget this face')}>
                        <Button
                            size="icon-xs"
                            variant="ghost"
                            loading={saving}
                            onClick={() => void run(() => onRemove(face))}
                            data-testid="face-remove"
                        >
                            <Trash2Icon />
                        </Button>
                    </Tooltip>
                ) : null}
            </div>
        </div>
    );
}

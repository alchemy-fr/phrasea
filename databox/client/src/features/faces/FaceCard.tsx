'use client';

import {useCallback, useState} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {CheckIcon, ExternalLinkIcon, Trash2Icon} from 'lucide-react';
import type {
    Asset,
    AttributeEntity,
    AttributeOrigin,
    FaceBox,
} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    getAttributeEntities,
    getAttributeEntity,
    postAttributeEntity,
} from '@/lib/api/metadata';
import {AsyncCombobox, type ComboOption} from '@/components/form/AsyncCombobox';
import {EntityChip} from '@/components/chips';
import {Button} from '@/components/ui/button';
import {Badge} from '@/components/ui/misc';
import {Tooltip} from '@/components/ui/overlays';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import {entityLabel} from '@/features/attributes/types/registry';
import {FaceCrop} from './FaceCrop';

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

function personOption(e: AttributeEntity): ComboOption<AttributeEntity> {
    return {
        value: e.id,
        label: entityLabel(e),
        render: <EntityChip entity={e} size="sm" />,
        item: e,
    };
}

export function isUnknownPerson(
    entity: FaceEntity | null | undefined
): boolean {
    return (
        !entity ||
        entity.status === AttributeEntityStatus.Pending ||
        !entity.value
    );
}

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
    const [saving, setSaving] = useState(false);
    const unknown = isUnknownPerson(face.entity);
    const recognized = !unknown && face.origin !== 'human';
    const asset = face.asset;
    const person = unknown ? undefined : face.entity?.id;

    // Only the named persons: a pending one is an unknown person, giving the
    // face to it would leave the face unnamed
    const loadPersons = useCallback(
        async (query: string) => {
            const page = await getAttributeEntities({
                list: listId,
                query: query || undefined,
                status: AttributeEntityStatus.Approved,
            });

            return page.items.map(personOption);
        },
        [listId]
    );
    const resolvePerson = useCallback(
        async (id: string) => personOption(await getAttributeEntity(id)),
        []
    );

    const run = async (action: () => Promise<void>) => {
        setSaving(true);
        try {
            await action();
        } finally {
            setSaving(false);
        }
    };

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
                    <AsyncCombobox<AttributeEntity>
                        queryKey={['attribute-entities', listId, 'approved']}
                        loadOptions={loadPersons}
                        resolveValue={resolvePerson}
                        value={person}
                        disabled={!canEdit || saving || !listId}
                        placeholder={t(
                            'faces.group_name_placeholder',
                            'Who is this?'
                        )}
                        createLabel={query =>
                            t('faces.group_create', {
                                defaultValue: 'New person « {{name}} »',
                                name: query,
                            })
                        }
                        onCreate={
                            listId
                                ? async query => {
                                      setSaving(true);
                                      try {
                                          return personOption(
                                              unknown && face.entity && onName
                                                  ? await onName(face, query)
                                                  : await postAttributeEntity(
                                                        listId,
                                                        {
                                                            value: query,
                                                            status: AttributeEntityStatus.Approved,
                                                        }
                                                    )
                                          );
                                      } finally {
                                          setSaving(false);
                                      }
                                  }
                                : undefined
                        }
                        onChange={(value, option) => {
                            // The renamed unknown person is already the face's one
                            if (value && value !== face.entity?.id) {
                                void run(() =>
                                    onAssign(face, value, option?.label)
                                );
                            }
                        }}
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

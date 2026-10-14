'use client';

import {FormEvent, useEffect, useId, useState} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {CheckIcon, ExternalLinkIcon, XIcon} from 'lucide-react';
import {Asset, AssetFace, FaceIdentityOrigin} from '@/types/api';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {Badge} from '@/components/ui/misc';
import {Tooltip} from '@/components/ui/overlays';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import {FaceCrop} from './FaceCrop';

/** What the card needs of a face: the API resource, or the integration data summary */
export type FaceCardFace = Pick<
    AssetFace,
    'id' | 'box' | 'identity' | 'identityOrigin' | 'identityConfidence'
> & {asset: Asset};

/**
 * One detected face: its crop, the asset it comes from and its name, to set,
 * confirm (a recognized face becomes a reference once a user confirms it) or
 * remove.
 */
export function FaceCard<F extends FaceCardFace>({
    face,
    knownNames,
    onIdentify,
    cropSize,
    showAssetLink = true,
}: {
    face: F;
    /** Names already given in the workspace, suggested while typing */
    knownNames: string[];
    onIdentify: (face: F, identity: string | null) => Promise<void>;
    cropSize?: number;
    /** Off where the asset is already displayed (its own view) */
    showAssetLink?: boolean;
}) {
    const {t} = useTranslation();
    const listId = useId();
    const identity = face.identity ?? '';
    const [draft, setDraft] = useState(identity);
    const [saving, setSaving] = useState(false);

    // A name set elsewhere (propagation, refresh) replaces an untouched draft
    useEffect(() => {
        setDraft(identity);
    }, [identity]);

    const dirty = draft.trim() !== identity;
    const recognized = face.identityOrigin === FaceIdentityOrigin.Auto;
    const asset = face.asset;

    const save = async (value: string | null) => {
        setSaving(true);
        try {
            await onIdentify(face, value);
        } finally {
            setSaving(false);
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const value = draft.trim();
        if (value === identity && !recognized) {
            return;
        }
        void save(value || null);
    };

    return (
        <form
            onSubmit={submit}
            className={cn(
                'flex flex-col gap-2 rounded-lg border bg-card p-2',
                !identity && 'border-dashed'
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
                {identity ? (
                    recognized ? (
                        <Tooltip
                            content={t(
                                'faces.recognized_help',
                                'Recognized automatically from a face named by a user. Confirm it to make it a reference.'
                            )}
                        >
                            <Badge variant="warning">
                                {t('faces.recognized', 'Recognized')}
                                {face.identityConfidence != null
                                    ? ` · ${Math.round(face.identityConfidence * 100)}%`
                                    : null}
                            </Badge>
                        </Tooltip>
                    ) : (
                        <Badge variant="success">
                            {t('faces.named', 'Named')}
                        </Badge>
                    )
                ) : (
                    <Badge variant="muted">
                        {t('faces.unnamed', 'Unnamed')}
                    </Badge>
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

            <div className="flex items-center gap-1">
                <Input
                    value={draft}
                    onChange={e => setDraft(e.target.value)}
                    list={listId}
                    placeholder={t('faces.name_placeholder', 'Who is this?')}
                    aria-label={t('faces.name', 'Name')}
                    className="h-8 text-xs"
                    disabled={saving}
                    data-testid="face-name"
                />
                <datalist id={listId}>
                    {knownNames.map(name => (
                        <option key={name} value={name} />
                    ))}
                </datalist>
                {dirty || recognized ? (
                    <Tooltip
                        content={
                            dirty
                                ? t('common.save', 'Save')
                                : t('faces.confirm', 'Confirm the name')
                        }
                    >
                        <Button
                            type="submit"
                            size="icon-xs"
                            variant={dirty ? 'default' : 'outline'}
                            loading={saving}
                            disabled={dirty && !draft.trim() && !identity}
                            data-testid="face-save"
                        >
                            <CheckIcon />
                        </Button>
                    </Tooltip>
                ) : null}
                {identity && !dirty ? (
                    <Tooltip content={t('faces.clear', 'Remove the name')}>
                        <Button
                            size="icon-xs"
                            variant="ghost"
                            loading={saving}
                            onClick={() => save(null)}
                            data-testid="face-clear"
                        >
                            <XIcon />
                        </Button>
                    </Tooltip>
                ) : null}
            </div>
        </form>
    );
}

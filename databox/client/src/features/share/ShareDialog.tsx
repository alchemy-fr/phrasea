'use client';

import {useId, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {
    ChevronDownIcon,
    CodeIcon,
    CopyIcon,
    ExternalLinkIcon,
    PencilIcon,
    PlusIcon,
    XIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import {
    AssetType,
    EntityName,
    type Asset,
    type RenditionDefinition,
    type Share,
    type ShareAlternateUrl,
} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Button} from '@/components/ui/button';
import {Checkbox, LabeledControl, Switch} from '@/components/ui/controls';
import {Input, FormRow} from '@/components/ui/input';
import {InlineLoader} from '@/components/ui/loader';
import {CopyButton} from '@/components/ui/copy-button';
import {Badge} from '@/components/ui/misc';
import {Tooltip} from '@/components/ui/overlays';
import {FileKindIcon} from '@/components/chips';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {
    createShare,
    deleteShare,
    getAssetShares,
    getRenditionDefinitions,
    updateShare,
} from '@/lib/api/misc';
import {fetchAllPages} from '@/lib/api/hydra';
import {routes} from '@/lib/routes';
import {formatDateTime} from '@/lib/utils/format';
import {copyToClipboard} from '@/lib/utils/misc';
import {toastError} from '@/lib/utils/errors';
import {cn} from '@/lib/utils/cn';
import {ShareSocials} from './ShareSocials';
import {EmbedDialog} from './EmbedDialog';
import {EventCategory, ShareEvent, track} from '@/lib/analytics/events';

export function getShareUrl(share: Share): string {
    return `${window.location.origin}${routes.share(share.id, share.token)}`;
}

/** Whether the share holds exactly these assets (sorted IDs) */
export function isShareOf(share: Share, assetIds: string[]): boolean {
    const ids = (share.assets ?? []).map(a => a.id).sort();

    return (
        ids.length === assetIds.length &&
        ids.every((id, i) => id === assetIds[i])
    );
}

/** Whether the share holds every one of these assets (and maybe others) */
export function shareIncludes(share: Share, assetIds: string[]): boolean {
    const ids = new Set((share.assets ?? []).map(a => a.id));

    return assetIds.every(id => ids.has(id));
}

/** Name and validity dates, as typed in the form */
type ShareFormValues = {
    name: string;
    startsAt: string;
    expiresAt: string;
};

const emptyForm: ShareFormValues = {name: '', startsAt: '', expiresAt: ''};

/** ISO date → value of a `datetime-local` input, in the local time zone */
function toInputDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function fromInputDateTime(value: string): string | null {
    return value ? new Date(value).toISOString() : null;
}

function formOf(share: Share): ShareFormValues {
    return {
        name: share.name ?? '',
        startsAt: toInputDateTime(share.startsAt),
        expiresAt: toInputDateTime(share.expiresAt),
    };
}

/**
 * Public links of one or several assets (of the same workspace). A link
 * shares a set of assets: every link holding the selected assets is listed,
 * including the links of a larger set.
 */
export function ShareDialog({
    open,
    onOpenChange,
    assets,
}: ModalProps & {assets: Asset[]}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const {openModal} = useModals();
    const assetIds = useMemo(() => assets.map(a => a.id).sort(), [assets]);
    const single = assets.length === 1 ? assets[0] : undefined;
    const queryKey = ['shares', assetIds.join(',')];
    const [advanced, setAdvanced] = useState<boolean>();
    const [creating, setCreating] = useState(false);
    const [form, setForm] = useState<ShareFormValues>(emptyForm);
    // Rendition definitions of the link being created; undefined: all of them
    const [renditionIds, setRenditionIds] = useState<string[]>();

    const shares = useQuery({
        queryKey,
        queryFn: async () =>
            (await getAssetShares(assetIds[0])).filter(s =>
                shareIncludes(s, assetIds)
            ),
    });
    const list = useMemo(() => shares.data ?? [], [shares.data]);
    const workspaceId = assets[0]?.workspace.id;
    const target = assets.every(a => a.storyCollection)
        ? AssetType.Story
        : assets.every(a => !a.storyCollection)
          ? AssetType.Asset
          : undefined;
    // The renditions a link may deliver: the ones of a public policy
    const definitions = useQuery({
        queryKey: ['rendition-definitions', workspaceId, 'shareable', target],
        queryFn: async () =>
            (
                await fetchAllPages(
                    getRenditionDefinitions({
                        workspaceIds: [workspaceId],
                        target,
                        limit: 100,
                    }),
                    url => getRenditionDefinitions({url})
                )
            ).filter(d => typeof d.policy === 'object' && d.policy?.public),
        enabled: !!workspaceId,
    });
    const shareable = useMemo(() => definitions.data ?? [], [definitions.data]);
    const selectedRenditions = renditionIds ?? shareable.map(d => d.id);
    const toggleRendition = (id: string, on: boolean) =>
        setRenditionIds(
            on
                ? [...selectedRenditions, id]
                : selectedRenditions.filter(r => r !== id)
        );

    // The plain "public link" of exactly these assets, driven by a switch
    const simpleShare =
        list.length === 1 &&
        isShareOf(list[0], assetIds) &&
        !list[0].name &&
        !list[0].expiresAt &&
        !list[0].startsAt
            ? list[0]
            : undefined;
    const isSimple = list.length === 0 || !!simpleShare;
    const advancedMode = advanced ?? !isSimple;

    const setShares = (fn: (prev: Share[]) => Share[]) =>
        queryClient.setQueryData<Share[]>(queryKey, prev => fn(prev ?? []));
    const create = useMutation({
        mutationFn: (data: Parameters<typeof createShare>[1]) =>
            createShare(assetIds, data),
        onSuccess: share => {
            track(EventCategory.Share, ShareEvent.Create, {
                value: assetIds.length,
            });
            setShares(prev => [...prev, share]);
            setCreating(false);
            setForm(emptyForm);
            setRenditionIds(undefined);
        },
        onError: (e: unknown) => toastError(e),
    });
    const update = useMutation({
        mutationFn: ({
            id,
            data,
        }: {
            id: string;
            data: Parameters<typeof updateShare>[1];
        }) => updateShare(id, data),
        // A link only stays listed while it holds the selected assets
        onSuccess: share => {
            track(EventCategory.Share, ShareEvent.Update);
            setShares(prev => {
                const others = prev.filter(s => s.id !== share.id);
                if (!shareIncludes(share, assetIds)) {
                    return others;
                }

                return prev.some(s => s.id === share.id)
                    ? prev.map(s => (s.id === share.id ? share : s))
                    : [...others, share];
            });
        },
        onError: (e: unknown) => toastError(e),
    });
    const setAssets = (share: Share, ids: string[], onSuccess?: () => void) =>
        update.mutate(
            {
                id: share.id,
                data: {assets: ids.map(id => `/${EntityName.Asset}/${id}`)},
            },
            {onSuccess}
        );
    const removeAsset = (share: Share, assetId: string) => {
        const previous = share.assets.map(a => a.id);
        setAssets(
            share,
            previous.filter(id => id !== assetId),
            () =>
                toast.success(
                    t('share.assets.removed', 'Asset removed from the link'),
                    {
                        action: {
                            label: t('common.undo', 'Undo'),
                            onClick: () => setAssets(share, previous),
                        },
                    }
                )
        );
    };
    const revoke = useMutation({
        mutationFn: (id: string) => deleteShare(id),
        onSuccess: (_d, id) => {
            track(EventCategory.Share, ShareEvent.Delete);
            setShares(prev => prev.filter(s => s.id !== id));
        },
        onError: (e: unknown) => toastError(e),
    });

    const shareTitle = (share: Share) =>
        share.name ||
        assets
            .map(a => a.name)
            .filter(Boolean)
            .join(', ');
    const cancelCreate = () => {
        setCreating(false);
        setForm(emptyForm);
        setRenditionIds(undefined);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size={'md'}
            title={
                single
                    ? t('share.title', 'Share asset')
                    : t('share.title_multiple', 'Share {{count}} assets', {
                          count: assets.length,
                      })
            }
            cancelLabel={t('common.close', 'Close')}
            hideSubmit={advancedMode || !simpleShare}
            submitLabel={t('share.copy_link', 'Copy link')}
            submitIcon={<CopyIcon />}
            // The new-link form holds what the user typed; the rest applies live
            dirty={
                creating &&
                (!!form.name.trim() ||
                    !!form.startsAt ||
                    !!form.expiresAt ||
                    renditionIds !== undefined)
            }
            bodyClassName="space-y-4"
            onSubmit={async () => {
                await copyToClipboard(getShareUrl(simpleShare!));
                toast.success(t('share.copied', 'Link copied to clipboard'));
            }}
        >
            {shares.isLoading ? <InlineLoader /> : null}
            {!advancedMode && shares.isSuccess ? (
                <>
                    <LabeledControl
                        label={t('share.public_link', 'Create a public link')}
                    >
                        <Switch
                            checked={!!simpleShare}
                            disabled={create.isPending || revoke.isPending}
                            onCheckedChange={v =>
                                v
                                    ? create.mutate({})
                                    : simpleShare &&
                                      revoke.mutate(simpleShare.id)
                            }
                        />
                    </LabeledControl>
                    {simpleShare ? (
                        <>
                            <ShareLink share={simpleShare} single={single} />
                            <ShareAssets
                                share={simpleShare}
                                onRemove={assetId =>
                                    removeAsset(simpleShare, assetId)
                                }
                                removing={update.isPending}
                            />
                            <ShareSocials
                                url={getShareUrl(simpleShare)}
                                title={shareTitle(simpleShare)}
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    openModal(EmbedDialog, {
                                        share: simpleShare,
                                        assets,
                                    })
                                }
                            >
                                <CodeIcon /> {t('share.embed', 'Embed code')}
                            </Button>
                        </>
                    ) : null}
                    <button
                        type="button"
                        className="block text-xs text-primary hover:underline"
                        onClick={() => {
                            setAdvanced(true);
                            // Nothing to list yet: go straight to the form
                            setCreating(list.length === 0);
                        }}
                    >
                        {t(
                            'share.advanced_mode',
                            'Advanced: multiple links with audience and dates'
                        )}
                    </button>
                </>
            ) : null}
            {advancedMode && shares.isSuccess ? (
                <>
                    {creating ? (
                        <div className="space-y-3 rounded-md border border-dashed p-3">
                            <ShareFields
                                values={form}
                                onChange={setForm}
                                autoFocus
                            />
                            {shareable.length > 0 ? (
                                <FormRow
                                    label={t(
                                        'share.create.renditions',
                                        'Shared renditions'
                                    )}
                                    help={t(
                                        'share.create.renditions_help',
                                        'Only the checked renditions can be viewed and downloaded from this link.'
                                    )}
                                    error={
                                        selectedRenditions.length === 0
                                            ? t(
                                                  'share.create.renditions_none',
                                                  'Select at least one rendition'
                                              )
                                            : undefined
                                    }
                                >
                                    <ul
                                        data-testid="share-create-renditions"
                                        className="flex flex-col gap-0.5"
                                    >
                                        {shareable.map(d => (
                                            <li key={d.id}>
                                                <label
                                                    className={cn(
                                                        'flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1 text-sm hover:bg-accent',
                                                        selectedRenditions.includes(
                                                            d.id
                                                        ) && 'bg-accent/60'
                                                    )}
                                                >
                                                    <Checkbox
                                                        checked={selectedRenditions.includes(
                                                            d.id
                                                        )}
                                                        onCheckedChange={v =>
                                                            toggleRendition(
                                                                d.id,
                                                                v === true
                                                            )
                                                        }
                                                    />
                                                    <span className="min-w-0 flex-1 truncate">
                                                        {d.displayName}
                                                    </span>
                                                </label>
                                            </li>
                                        ))}
                                    </ul>
                                </FormRow>
                            ) : null}
                            <div className="flex justify-end gap-2">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={cancelCreate}
                                >
                                    {t('common.cancel', 'Cancel')}
                                </Button>
                                <Button
                                    size="sm"
                                    loading={create.isPending}
                                    disabled={
                                        shareable.length > 0 &&
                                        selectedRenditions.length === 0
                                    }
                                    onClick={() =>
                                        create.mutate({
                                            name: form.name.trim() || undefined,
                                            startsAt:
                                                fromInputDateTime(
                                                    form.startsAt
                                                ) ?? undefined,
                                            expiresAt:
                                                fromInputDateTime(
                                                    form.expiresAt
                                                ) ?? undefined,
                                            // Every rendition: no selection
                                            renditionDefinitionIds:
                                                selectedRenditions.length <
                                                shareable.length
                                                    ? selectedRenditions
                                                    : undefined,
                                        })
                                    }
                                >
                                    {t('common.create', 'Create')}
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setCreating(true)}
                        >
                            <PlusIcon />{' '}
                            {t('share.create.button', 'Create new share link')}
                        </Button>
                    )}
                    <ul className="space-y-2">
                        {list.map(s => (
                            <ShareItem
                                key={s.id}
                                share={s}
                                single={single}
                                definitions={shareable}
                                onEmbed={() =>
                                    openModal(EmbedDialog, {share: s, assets})
                                }
                                onRevoke={() => revoke.mutate(s.id)}
                                revoking={
                                    revoke.isPending &&
                                    revoke.variables === s.id
                                }
                                onSave={values =>
                                    update.mutateAsync({
                                        id: s.id,
                                        data: {
                                            name: values.name.trim() || null,
                                            startsAt: fromInputDateTime(
                                                values.startsAt
                                            ),
                                            expiresAt: fromInputDateTime(
                                                values.expiresAt
                                            ),
                                        },
                                    })
                                }
                                onRemoveAsset={assetId =>
                                    removeAsset(s, assetId)
                                }
                                removing={
                                    update.isPending &&
                                    update.variables?.id === s.id
                                }
                            />
                        ))}
                    </ul>
                </>
            ) : null}
        </FormDialog>
    );
}

/** A share link: its details, editable in place, and its URLs */
function ShareItem({
    share,
    single,
    definitions,
    onEmbed,
    onRevoke,
    revoking,
    onSave,
    onRemoveAsset,
    removing,
}: {
    share: Share;
    single: Asset | undefined;
    definitions: RenditionDefinition[];
    onEmbed: () => void;
    onRevoke: () => void;
    revoking: boolean;
    onSave: (values: ShareFormValues) => Promise<unknown>;
    onRemoveAsset: (assetId: string) => void;
    removing: boolean;
}) {
    const {t, i18n} = useTranslation();
    const [editing, setEditing] = useState<ShareFormValues>();
    const [saving, setSaving] = useState(false);
    const assetCount = share.assets?.length ?? 0;

    const save = async () => {
        if (!editing) {
            return;
        }
        setSaving(true);
        try {
            await onSave(editing);
            setEditing(undefined);
        } catch {
            // Reported by the mutation
        } finally {
            setSaving(false);
        }
    };

    return (
        <li data-testid="share-item" className="rounded-md border p-3">
            <div className="mb-1 flex items-center gap-2">
                <span className="flex-1 truncate text-sm font-medium">
                    {share.name || t('share.unnamed', 'Public link')}
                </span>
                <Badge variant="muted" data-testid="share-assets-count">
                    {t('share.assets_count', '{{count}} assets', {
                        count: assetCount,
                    })}
                </Badge>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={() =>
                        setEditing(editing ? undefined : formOf(share))
                    }
                    aria-label={t('common.edit', 'Edit')}
                    aria-pressed={!!editing}
                >
                    <PencilIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={onEmbed}
                    aria-label={t('share.embed', 'Embed code')}
                >
                    <CodeIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    onClick={onRevoke}
                    loading={revoking}
                >
                    <XIcon /> {t('share.revoke', 'Revoke')}
                </Button>
            </div>
            {editing ? (
                <div className="mb-2 space-y-3 rounded-md border border-dashed p-3">
                    <ShareFields
                        values={editing}
                        onChange={setEditing}
                        autoFocus
                    />
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditing(undefined)}
                        >
                            {t('common.cancel', 'Cancel')}
                        </Button>
                        <Button size="sm" loading={saving} onClick={save}>
                            {t('common.save', 'Save')}
                        </Button>
                    </div>
                </div>
            ) : share.startsAt || share.expiresAt ? (
                <div className="mb-1 text-xs text-muted-foreground">
                    {share.startsAt
                        ? `${t('share.starts_at', 'From')} ${formatDateTime(share.startsAt, 'short', i18n.language)} `
                        : ''}
                    {share.expiresAt
                        ? `${t('share.expires_at', 'until')} ${formatDateTime(share.expiresAt, 'short', i18n.language)}`
                        : ''}
                </div>
            ) : null}
            {share.renditionsRestricted ? (
                <div
                    data-testid="share-renditions"
                    className="mb-1 flex flex-wrap items-center gap-1 text-xs text-muted-foreground"
                >
                    {t('share.item.renditions', 'Shared renditions')}
                    {share.renditionDefinitions?.length ? (
                        share.renditionDefinitions.map(d => (
                            <Badge key={d.id} variant="muted">
                                {definitions.find(def => def.id === d.id)
                                    ?.displayName ?? d.id}
                            </Badge>
                        ))
                    ) : (
                        <Badge variant="muted">
                            {t('share.item.renditions_none', 'None')}
                        </Badge>
                    )}
                </div>
            ) : null}
            <ShareLink share={share} single={single} />
            <ShareAssets
                share={share}
                onRemove={onRemoveAsset}
                removing={removing}
            />
        </li>
    );
}

function ShareFields({
    values,
    onChange,
    autoFocus,
}: {
    values: ShareFormValues;
    onChange: (values: ShareFormValues) => void;
    autoFocus?: boolean;
}) {
    const {t} = useTranslation();
    const id = useId();
    const set = (key: keyof ShareFormValues, value: string) =>
        onChange({...values, [key]: value});

    return (
        <>
            <FormRow
                label={t('share.create.name', 'Name / audience')}
                htmlFor={`${id}-name`}
            >
                <Input
                    id={`${id}-name`}
                    autoFocus={autoFocus}
                    value={values.name}
                    onChange={e => set('name', e.target.value)}
                />
            </FormRow>
            <div className="grid grid-cols-2 gap-3">
                <FormRow
                    label={t('share.create.starts_at', 'Starts at')}
                    htmlFor={`${id}-starts`}
                >
                    <Input
                        id={`${id}-starts`}
                        type="datetime-local"
                        value={values.startsAt}
                        onChange={e => set('startsAt', e.target.value)}
                    />
                </FormRow>
                <FormRow
                    label={t('share.create.expires_at', 'Expires at')}
                    htmlFor={`${id}-expires`}
                >
                    <Input
                        id={`${id}-expires`}
                        type="datetime-local"
                        value={values.expiresAt}
                        onChange={e => set('expiresAt', e.target.value)}
                    />
                </FormRow>
            </div>
        </>
    );
}

/** The renditions a link delivers for an asset */
function renditionsOf(share: Share, assetId: string): ShareAlternateUrl[] {
    const single = (share.assets?.length ?? 0) <= 1;

    return (share.alternateUrls ?? []).filter(a =>
        a.assetId ? a.assetId === assetId : single
    );
}

/**
 * The link to copy: the share page, or — for a single asset — one of the
 * renditions the link delivers for it, picked from the label.
 */
function ShareLink({share, single}: {share: Share; single: Asset | undefined}) {
    const {t} = useTranslation();
    const [choice, setChoice] = useState<string>('asset');
    const renditions = single ? renditionsOf(share, single.id) : [];
    const current = renditions.find(a => (a.id ?? a.name) === choice);
    const url = current ? current.url : getShareUrl(share);
    const label = current
        ? current.displayName || current.name
        : t('share.rendition.asset', 'Asset page');

    return (
        <div
            data-testid="share-link"
            className="flex items-center gap-2 rounded-md border bg-muted/40 pl-1"
        >
            {renditions.length > 0 ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="shrink-0 font-medium"
                            data-testid="share-link-choice"
                        >
                            {label} <ChevronDownIcon />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem onSelect={() => setChoice('asset')}>
                            {t('share.rendition.asset', 'Asset page')}
                        </DropdownMenuItem>
                        {renditions.map(a => (
                            <DropdownMenuItem
                                key={a.id ?? a.name}
                                onSelect={() => setChoice(a.id ?? a.name)}
                            >
                                {a.displayName || a.name}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : (
                <span className="shrink-0 px-1.5 text-xs font-medium">
                    {label}
                </span>
            )}
            <code className="min-w-0 flex-1 truncate py-1.5 text-xs">
                {url}
            </code>
            <CopyButton value={url} />
            <Button variant="ghost" size="icon-xs" asChild>
                <a href={url} target="_blank" rel="noopener noreferrer">
                    <ExternalLinkIcon />
                </a>
            </Button>
        </div>
    );
}

/**
 * The assets of a link holding several: each one with the URLs of its
 * renditions to copy, and removable from the link.
 */
function ShareAssets({
    share,
    onRemove,
    removing,
}: {
    share: Share;
    onRemove: (assetId: string) => void;
    removing: boolean;
}) {
    const {t} = useTranslation();
    const assets = share.assets ?? [];
    if (assets.length < 2) {
        return null;
    }

    const copy = async (url: string) => {
        await copyToClipboard(url);
        toast.success(t('share.copied', 'Link copied to clipboard'));
    };

    return (
        <ul
            data-testid="share-assets"
            className="mt-2 max-h-[200px] space-y-1 overflow-y-auto"
        >
            {assets.map(asset => {
                const renditions = renditionsOf(share, asset.id);
                const file = asset.thumbnail?.file;
                const type = file?.type || asset.source?.type;
                const thumb =
                    file?.url && type?.startsWith('image/')
                        ? file.url
                        : undefined;

                return (
                    <li
                        key={asset.id}
                        data-testid="share-asset-row"
                        className="flex items-center gap-2 rounded-md px-1 py-0.5 hover:bg-accent/60"
                    >
                        <span className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded bg-muted">
                            {thumb ? (
                                // eslint-disable-next-line @next/next/no-img-element
                                <img
                                    src={thumb}
                                    alt=""
                                    className="size-full object-cover"
                                />
                            ) : (
                                <FileKindIcon
                                    mimeType={type}
                                    className="size-5"
                                />
                            )}
                        </span>
                        <span className="min-w-0 flex-1 truncate text-sm">
                            {asset.name}
                        </span>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    disabled={renditions.length === 0}
                                    data-testid="share-asset-copy"
                                >
                                    <CopyIcon />{' '}
                                    {t('share.assets.copy', 'Copy URL')}
                                    <ChevronDownIcon />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {renditions.map(a => (
                                    <DropdownMenuItem
                                        key={a.id ?? a.name}
                                        onSelect={() => copy(a.url)}
                                    >
                                        {a.displayName || a.name}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Tooltip
                            content={t(
                                'share.assets.remove',
                                'Remove from this link'
                            )}
                        >
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                className="text-destructive"
                                disabled={removing}
                                onClick={() => onRemove(asset.id)}
                                aria-label={t(
                                    'share.assets.remove',
                                    'Remove from this link'
                                )}
                                data-testid="share-asset-remove"
                            >
                                <XIcon />
                            </Button>
                        </Tooltip>
                    </li>
                );
            })}
        </ul>
    );
}

'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {
    CodeIcon,
    CopyIcon,
    ExternalLinkIcon,
    PlusIcon,
    XIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import {AssetType, type Asset, type Share} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Button} from '@/components/ui/button';
import {Checkbox, LabeledControl, Switch} from '@/components/ui/controls';
import {Input, FormRow} from '@/components/ui/input';
import {InlineLoader} from '@/components/ui/loader';
import {CopyButton} from '@/components/ui/copy-button';
import {SimpleSelect} from '@/components/ui/select';
import {Badge} from '@/components/ui/misc';
import {
    createShare,
    deleteShare,
    getAssetShares,
    getRenditionDefinitions,
} from '@/lib/api/misc';
import {routes} from '@/lib/routes';
import {formatDateTime} from '@/lib/utils/format';
import {copyToClipboard} from '@/lib/utils/misc';
import {toastError} from '@/lib/utils/errors';
import {cn} from '@/lib/utils/cn';
import {ShareSocials} from './ShareSocials';
import {EmbedDialog} from './EmbedDialog';

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

/**
 * Public links of one or several assets (of the same workspace). A link
 * shares the whole set: only the links holding exactly these assets are
 * listed (an asset may also belong to links of other sets).
 */
export function ShareDialog({
    open,
    onOpenChange,
    assets,
}: ModalProps & {assets: Asset[]}) {
    const {t, i18n} = useTranslation();
    const queryClient = useQueryClient();
    const {openModal} = useModals();
    const assetIds = useMemo(() => assets.map(a => a.id).sort(), [assets]);
    const single = assets.length === 1 ? assets[0] : undefined;
    const queryKey = ['shares', assetIds.join(',')];
    const [advanced, setAdvanced] = useState<boolean>();
    const [creating, setCreating] = useState(false);
    const [newName, setNewName] = useState('');
    const [startsAt, setStartsAt] = useState('');
    const [expiresAt, setExpiresAt] = useState('');
    const [rendition, setRendition] = useState<string>('asset');
    // Rendition definitions of the link being created; undefined: all of them
    const [renditionIds, setRenditionIds] = useState<string[]>();

    const shares = useQuery({
        queryKey,
        queryFn: async () =>
            (await getAssetShares(assetIds[0])).filter(s =>
                isShareOf(s, assetIds)
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
                await getRenditionDefinitions({
                    workspaceIds: [workspaceId],
                    target,
                })
            ).items.filter(
                d => typeof d.policy === 'object' && d.policy?.public
            ),
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
    const definitionName = (id: string) =>
        shareable.find(d => d.id === id)?.displayName ?? '';
    const simpleShare =
        list.length === 1 &&
        !list[0].name &&
        !list[0].expiresAt &&
        !list[0].startsAt
            ? list[0]
            : undefined;
    const isSimple = list.length === 0 || !!simpleShare;
    const advancedMode = advanced ?? !isSimple;

    const create = useMutation({
        mutationFn: (data: Parameters<typeof createShare>[1]) =>
            createShare(assetIds, data),
        onSuccess: share => {
            queryClient.setQueryData<Share[]>(queryKey, prev => [
                ...(prev ?? []),
                share,
            ]);
            setCreating(false);
            setNewName('');
            setStartsAt('');
            setExpiresAt('');
            setRenditionIds(undefined);
        },
        onError: (e: unknown) => toastError(e),
    });
    const revoke = useMutation({
        mutationFn: (id: string) => deleteShare(id),
        onSuccess: (_d, id) =>
            queryClient.setQueryData<Share[]>(queryKey, prev =>
                (prev ?? []).filter(s => s.id !== id)
            ),
        onError: (e: unknown) => toastError(e),
    });

    const urlFor = (share: Share) => {
        const alt = share.alternateUrls?.find(a => a.name === rendition);

        return alt ? alt.url : getShareUrl(share);
    };
    // A rendition can only be picked for a single asset: a link of several
    // assets always opens the share page. Links may deliver different
    // renditions: every rendition of a link is offered, a link lacking the
    // picked one falls back to the share page.
    const renditionOptions = useMemo(() => {
        const names = new Set<string>();
        if (single) {
            list.forEach(s =>
                (s.alternateUrls ?? [])
                    .filter(a => !a.assetId || a.assetId === single.id)
                    .forEach(a => names.add(a.name))
            );
        }

        return [
            {value: 'asset', label: t('share.rendition.asset', 'Asset page')},
            ...[...names].map(name => ({value: name, label: name})),
        ];
    }, [list, single, t]);
    const shareTitle = (share: Share) =>
        share.name ||
        assets
            .map(a => a.name)
            .filter(Boolean)
            .join(', ');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size={advancedMode ? 'lg' : 'sm'}
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
                (!!newName.trim() ||
                    !!startsAt ||
                    !!expiresAt ||
                    renditionIds !== undefined)
            }
            bodyClassName="space-y-4"
            onSubmit={async () => {
                await copyToClipboard(urlFor(simpleShare!));
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
                            {renditionOptions.length > 1 ? (
                                <FormRow
                                    label={t(
                                        'share.rendition.label',
                                        'Shared rendition'
                                    )}
                                >
                                    <SimpleSelect
                                        value={rendition}
                                        onValueChange={setRendition}
                                        options={renditionOptions}
                                    />
                                </FormRow>
                            ) : null}
                            <ShareUrl url={urlFor(simpleShare)} />
                            <ShareSocials
                                url={urlFor(simpleShare)}
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
                        className="text-xs text-primary hover:underline"
                        onClick={() => setAdvanced(true)}
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
                    {renditionOptions.length > 1 ? (
                        <FormRow
                            label={t(
                                'share.rendition.label',
                                'Shared rendition'
                            )}
                        >
                            <SimpleSelect
                                value={rendition}
                                onValueChange={setRendition}
                                options={renditionOptions}
                                className="w-64"
                            />
                        </FormRow>
                    ) : null}
                    <ul className="space-y-2">
                        {list.map(s => (
                            <li key={s.id} className="rounded-md border p-3">
                                <div className="mb-1 flex items-center gap-2">
                                    <span className="flex-1 truncate text-sm font-medium">
                                        {s.name ||
                                            t('share.unnamed', 'Public link')}
                                    </span>
                                    <Button
                                        variant="ghost"
                                        size="icon-xs"
                                        onClick={() =>
                                            openModal(EmbedDialog, {
                                                share: s,
                                                assets,
                                            })
                                        }
                                        aria-label={t(
                                            'share.embed',
                                            'Embed code'
                                        )}
                                    >
                                        <CodeIcon />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={() => revoke.mutate(s.id)}
                                        loading={
                                            revoke.isPending &&
                                            revoke.variables === s.id
                                        }
                                    >
                                        <XIcon /> {t('share.revoke', 'Revoke')}
                                    </Button>
                                </div>
                                {s.startsAt || s.expiresAt ? (
                                    <div className="mb-1 text-xs text-muted-foreground">
                                        {s.startsAt
                                            ? `${t('share.starts_at', 'From')} ${formatDateTime(s.startsAt, 'short', i18n.language)} `
                                            : ''}
                                        {s.expiresAt
                                            ? `${t('share.expires_at', 'until')} ${formatDateTime(s.expiresAt, 'short', i18n.language)}`
                                            : ''}
                                    </div>
                                ) : null}
                                {s.renditionDefinitions?.length ? (
                                    <div
                                        data-testid="share-renditions"
                                        className="mb-1 flex flex-wrap items-center gap-1 text-xs text-muted-foreground"
                                    >
                                        {t(
                                            'share.item.renditions',
                                            'Shared renditions'
                                        )}
                                        {s.renditionDefinitions.map(d => (
                                            <Badge key={d.id} variant="muted">
                                                {definitionName(d.id) || d.id}
                                            </Badge>
                                        ))}
                                    </div>
                                ) : null}
                                <ShareUrl url={urlFor(s)} />
                            </li>
                        ))}
                    </ul>
                    {creating ? (
                        <div className="space-y-3 rounded-md border border-dashed p-3">
                            <FormRow
                                label={t(
                                    'share.create.name',
                                    'Name / audience'
                                )}
                            >
                                <Input
                                    autoFocus
                                    value={newName}
                                    onChange={e => setNewName(e.target.value)}
                                />
                            </FormRow>
                            <div className="grid grid-cols-2 gap-3">
                                <FormRow
                                    label={t(
                                        'share.create.starts_at',
                                        'Starts at'
                                    )}
                                >
                                    <Input
                                        type="datetime-local"
                                        value={startsAt}
                                        onChange={e =>
                                            setStartsAt(e.target.value)
                                        }
                                    />
                                </FormRow>
                                <FormRow
                                    label={t(
                                        'share.create.expires_at',
                                        'Expires at'
                                    )}
                                >
                                    <Input
                                        type="datetime-local"
                                        value={expiresAt}
                                        onChange={e =>
                                            setExpiresAt(e.target.value)
                                        }
                                    />
                                </FormRow>
                            </div>
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
                                    onClick={() => {
                                        setCreating(false);
                                        setRenditionIds(undefined);
                                    }}
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
                                            name: newName || undefined,
                                            // Every rendition: no selection
                                            renditionDefinitionIds:
                                                selectedRenditions.length <
                                                shareable.length
                                                    ? selectedRenditions
                                                    : undefined,
                                            startsAt: startsAt
                                                ? new Date(
                                                      startsAt
                                                  ).toISOString()
                                                : undefined,
                                            expiresAt: expiresAt
                                                ? new Date(
                                                      expiresAt
                                                  ).toISOString()
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
                </>
            ) : null}
        </FormDialog>
    );
}

function ShareUrl({url}: {url: string}) {
    return (
        <div className="flex items-center gap-1 rounded-md border bg-muted/40 pl-2">
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

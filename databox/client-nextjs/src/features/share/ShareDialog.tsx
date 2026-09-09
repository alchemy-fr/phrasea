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
import type {Asset, Share} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {Input, FormRow} from '@/components/ui/input';
import {InlineLoader} from '@/components/ui/loader';
import {CopyButton} from '@/components/ui/copy-button';
import {SimpleSelect} from '@/components/ui/select';
import {createShare, deleteShare, getAssetShares} from '@/lib/api/misc';
import {routes} from '@/lib/routes';
import {formatDateTime} from '@/lib/utils/format';
import {copyToClipboard} from '@/lib/utils/misc';
import {ShareSocials} from './ShareSocials';
import {EmbedDialog} from './EmbedDialog';

export function getShareUrl(share: Share): string {
    return `${window.location.origin}${routes.share(share.id, share.token)}`;
}

export function ShareDialog({
    open,
    onOpenChange,
    asset,
}: ModalProps & {asset: Asset}) {
    const {t, i18n} = useTranslation();
    const queryClient = useQueryClient();
    const {openModal} = useModals();
    const queryKey = ['shares', asset.id];
    const [advanced, setAdvanced] = useState<boolean>();
    const [creating, setCreating] = useState(false);
    const [newName, setNewName] = useState('');
    const [startsAt, setStartsAt] = useState('');
    const [expiresAt, setExpiresAt] = useState('');
    const [rendition, setRendition] = useState<string>('asset');

    const shares = useQuery({
        queryKey,
        queryFn: () => getAssetShares(asset.id),
    });
    const list = useMemo(() => shares.data ?? [], [shares.data]);
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
        mutationFn: (data: Partial<Share>) => createShare(asset.id, data),
        onSuccess: share => {
            queryClient.setQueryData<Share[]>(queryKey, prev => [
                ...(prev ?? []),
                share,
            ]);
            setCreating(false);
            setNewName('');
            setStartsAt('');
            setExpiresAt('');
        },
        onError: (e: any) => toast.error(e?.message),
    });
    const revoke = useMutation({
        mutationFn: (id: string) => deleteShare(id),
        onSuccess: (_d, id) =>
            queryClient.setQueryData<Share[]>(queryKey, prev =>
                (prev ?? []).filter(s => s.id !== id)
            ),
        onError: (e: any) => toast.error(e?.message),
    });

    const urlFor = (share: Share) => {
        const alt = share.alternateUrls?.find(a => a.name === rendition);

        return alt ? alt.url : getShareUrl(share);
    };
    const renditionOptions = useMemo(() => {
        const first = list[0];
        const alts = first?.alternateUrls ?? [];

        return [
            {value: 'asset', label: t('share.rendition.asset', 'Asset page')},
            ...alts.map(a => ({value: a.name, label: a.name})),
        ];
    }, [list, t]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size={advancedMode ? 'lg' : 'sm'}>
                <DialogHeader>
                    <DialogTitle>{t('share.title', 'Share asset')}</DialogTitle>
                </DialogHeader>
                <DialogBody className="space-y-4">
                    {shares.isLoading ? <InlineLoader /> : null}
                    {!advancedMode && shares.isSuccess ? (
                        <>
                            <LabeledControl
                                label={t(
                                    'share.public_link',
                                    'Create a public link'
                                )}
                            >
                                <Switch
                                    checked={!!simpleShare}
                                    disabled={
                                        create.isPending || revoke.isPending
                                    }
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
                                        title={asset.name ?? ''}
                                    />
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            openModal(EmbedDialog, {
                                                share: simpleShare,
                                                asset,
                                            })
                                        }
                                    >
                                        <CodeIcon />{' '}
                                        {t('share.embed', 'Embed code')}
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
                                    <li
                                        key={s.id}
                                        className="rounded-md border p-3"
                                    >
                                        <div className="mb-1 flex items-center gap-2">
                                            <span className="flex-1 truncate text-sm font-medium">
                                                {s.name ||
                                                    t(
                                                        'share.unnamed',
                                                        'Public link'
                                                    )}
                                            </span>
                                            <Button
                                                variant="ghost"
                                                size="icon-xs"
                                                onClick={() =>
                                                    openModal(EmbedDialog, {
                                                        share: s,
                                                        asset,
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
                                                onClick={() =>
                                                    revoke.mutate(s.id)
                                                }
                                                loading={
                                                    revoke.isPending &&
                                                    revoke.variables === s.id
                                                }
                                            >
                                                <XIcon />{' '}
                                                {t('share.revoke', 'Revoke')}
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
                                            onChange={e =>
                                                setNewName(e.target.value)
                                            }
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
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setCreating(false)}
                                        >
                                            {t('common.cancel', 'Cancel')}
                                        </Button>
                                        <Button
                                            size="sm"
                                            loading={create.isPending}
                                            onClick={() =>
                                                create.mutate({
                                                    name: newName || undefined,
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
                                    {t(
                                        'share.create.button',
                                        'Create new share link'
                                    )}
                                </Button>
                            )}
                        </>
                    ) : null}
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.close', 'Close')}
                    </Button>
                    {!advancedMode && simpleShare ? (
                        <Button
                            onClick={async () => {
                                await copyToClipboard(urlFor(simpleShare));
                                toast.success(
                                    t(
                                        'share.copied',
                                        'Link copied to clipboard'
                                    )
                                );
                                onOpenChange(false);
                            }}
                        >
                            <CopyIcon /> {t('share.copy_link', 'Copy link')}
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
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

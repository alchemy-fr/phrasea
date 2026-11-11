'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import {addDays} from 'date-fns';
import {
    BanIcon,
    LinkIcon,
    PencilIcon,
    Share2Icon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';
import {
    ShareStatus,
    type Publication,
    type PublicationShare,
} from '@/types/api';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import {
    useModals,
    type ModalProps,
} from '@alchemy/next-ui/modals/ModalProvider';
import {Button} from '@alchemy/next-ui/ui/button';
import {CopyButton} from '@alchemy/next-ui/ui/copy-button';
import {useConfirm} from '@alchemy/next-ui/ui/confirm';
import {Input, Label} from '@alchemy/next-ui/ui/input';
import {Badge, EmptyState, Skeleton} from '@alchemy/next-ui/ui/misc';
import {cn} from '@alchemy/next-ui/utils/cn';
import {
    createPublicationShare,
    deletePublicationShare,
    getPublicationShares,
    revokePublicationShare,
    shareKeys,
    updatePublicationShare,
} from '@/lib/api/shares';
import {formatDateTime} from '@/lib/utils/format';
import {routes} from '@/lib/routes';
import {useTranslateField} from '@/lib/utils/translate';
import {fromLocalInput, toLocalInput} from '@/features/admin/config/dates';

/** Default lifetime of a new link */
const DEFAULT_DAYS = 30;

function defaultExpiration(): string {
    return toLocalInput(addDays(new Date(), DEFAULT_DAYS).toISOString());
}

/** The permalink of a share: the one given by the API, else on this client */
export function getShareUrl(share: PublicationShare): string {
    if (/^https?:\/\//.test(share.url)) {
        return share.url;
    }

    return `${window.location.origin}${routes.share(share.token)}`;
}

/** Opens the share links of a publication; for its operators */
export function PublicationShareButton({
    publication,
}: {
    publication: Pick<Publication, 'id' | 'title' | 'translations'>;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    return (
        <Button
            variant="outline"
            size="sm"
            data-testid="publication-share"
            onClick={() =>
                openModal(
                    PublicationSharesDialog,
                    {publication},
                    {key: `shares-${publication.id}`}
                )
            }
        >
            <Share2Icon /> {t('share.button', 'Share')}
        </Button>
    );
}

/**
 * The share links of a publication: each one opens it to whoever holds it,
 * whatever its visibility and its password, until it expires or is revoked.
 * The form creates a link; the changes apply right away.
 */
export function PublicationSharesDialog({
    open,
    onOpenChange,
    publication,
}: ModalProps & {
    publication: Pick<Publication, 'id' | 'title' | 'translations'>;
}) {
    const {t} = useTranslation();
    const translate = useTranslateField();
    const queryClient = useQueryClient();
    const [name, setName] = useState('');
    const [expiresAt, setExpiresAt] = useState(defaultExpiration);
    const queryKey = shareKeys.list(publication.id);
    const query = useQuery({
        queryKey,
        queryFn: () => getPublicationShares(publication.id),
    });

    const setShares = (
        update: (shares: PublicationShare[]) => PublicationShare[]
    ) =>
        queryClient.setQueryData<PublicationShare[]>(queryKey, shares =>
            update(shares ?? [])
        );

    const replace = (share: PublicationShare) =>
        setShares(shares => shares.map(s => (s.id === share.id ? share : s)));

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('share.title', 'Share "{{title}}"', {
                title: translate(publication, 'title'),
            })}
            description={t(
                'share.description',
                'A share link opens this publication to whoever holds it, even when it is disabled or protected, until it expires or is revoked. Its sub-publications are not shared.'
            )}
            submitLabel={t('share.create', 'Create link')}
            submitIcon={<LinkIcon />}
            cancelLabel={t('common.close', 'Close')}
            bodyClassName="space-y-6"
            onSubmit={async () => {
                const share = await createPublicationShare(publication.id, {
                    name: name.trim() || null,
                    expiresAt: fromLocalInput(expiresAt),
                });
                setShares(shares => [share, ...shares]);
                setName('');
                setExpiresAt(defaultExpiration());
                toast.success(t('share.created', 'Share link created'));

                // The dialog stays open on the new link
                return false;
            }}
        >
            <fieldset
                data-testid="share-create-form"
                className="grid gap-3 sm:grid-cols-2"
            >
                <div>
                    <Label htmlFor="share-name" className="mb-1.5">
                        {t('share.field.name', 'Name')}
                    </Label>
                    <Input
                        id="share-name"
                        value={name}
                        maxLength={255}
                        placeholder={t(
                            'share.field.name_placeholder',
                            'Recipient, purpose…'
                        )}
                        onChange={e => setName(e.target.value)}
                    />
                </div>
                <ExpirationInput
                    id="share-expires-at"
                    value={expiresAt}
                    onChange={setExpiresAt}
                />
            </fieldset>

            <ShareList
                shares={query.data}
                isPending={query.isPending}
                onChange={replace}
                onDelete={id =>
                    setShares(shares => shares.filter(s => s.id !== id))
                }
            />
        </FormDialog>
    );
}

function ExpirationInput({
    id,
    value,
    onChange,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
}) {
    const {t} = useTranslation();

    return (
        <div>
            <Label htmlFor={id} className="mb-1.5">
                {t('share.field.expires_at', 'Expires on')}
            </Label>
            <div className="flex gap-2">
                <Input
                    id={id}
                    type="datetime-local"
                    value={value}
                    onChange={e => onChange(e.target.value)}
                />
                {value ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('common.clear', 'Clear')}
                        data-testid={`${id}-clear`}
                        onClick={() => onChange('')}
                    >
                        <XIcon />
                    </Button>
                ) : null}
            </div>
            {!value ? (
                <p className="mt-1 text-xs text-muted-foreground">
                    {t(
                        'share.field.no_expiration_help',
                        'Without expiration, the link works until it is revoked.'
                    )}
                </p>
            ) : null}
        </div>
    );
}

function ShareList({
    shares,
    isPending,
    onChange,
    onDelete,
}: {
    shares: PublicationShare[] | undefined;
    isPending: boolean;
    onChange: (share: PublicationShare) => void;
    onDelete: (id: string) => void;
}) {
    const {t} = useTranslation();

    if (isPending) {
        return <Skeleton className="h-20 w-full" />;
    }
    if (!shares || shares.length === 0) {
        return (
            <EmptyState
                testId="share-empty"
                className="p-4"
                icon={<LinkIcon />}
                title={t('share.empty', 'No share link yet')}
            />
        );
    }

    return (
        <ul data-testid="share-list" className="divide-y rounded-md border">
            {shares.map(share => (
                <ShareItem
                    key={share.id}
                    share={share}
                    onChange={onChange}
                    onDelete={onDelete}
                />
            ))}
        </ul>
    );
}

function ShareItem({
    share,
    onChange,
    onDelete,
}: {
    share: PublicationShare;
    onChange: (share: PublicationShare) => void;
    onDelete: (id: string) => void;
}) {
    const {t, i18n} = useTranslation();
    const confirm = useConfirm();
    const {openModal} = useModals();
    const url = getShareUrl(share);
    const label = share.name || t('share.default_name', 'Share link');
    const revoked = share.status === ShareStatus.Revoked;
    const date = (value: string | null | undefined) =>
        formatDateTime(value, 'medium', i18n.language);

    const revoke = () =>
        confirm({
            title: t('share.revoke.title', 'Revoke this link?'),
            description: t(
                'share.revoke.description',
                'The link "{{name}}" will no longer open the publication. This cannot be undone.',
                {name: label}
            ),
            confirmLabel: t('share.revoke.confirm', 'Revoke'),
            destructive: true,
            onConfirm: async () => {
                onChange(await revokePublicationShare(share.id));
                toast.success(t('share.revoke.done', 'Share link revoked'));
            },
        });

    const remove = () =>
        confirm({
            title: t('share.delete.title', 'Delete this link?'),
            description: t(
                'share.delete.description',
                'The link "{{name}}" will be removed from the list.',
                {name: label}
            ),
            confirmLabel: t('common.delete', 'Delete'),
            destructive: true,
            onConfirm: async () => {
                await deletePublicationShare(share.id);
                onDelete(share.id);
                toast.success(t('share.delete.done', 'Share link deleted'));
            },
        });

    const edit = async () => {
        const updated = await openModal(ShareEditDialog, {share});
        if (updated) {
            onChange(updated);
        }
    };

    return (
        <li
            data-testid="share-item"
            data-status={share.status}
            className={cn(
                'flex flex-col gap-2 p-3 sm:flex-row sm:items-start',
                revoked && 'opacity-60'
            )}
        >
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="truncate font-medium">{label}</span>
                    <ShareStatusBadge status={share.status} />
                </div>
                <div className="flex min-w-0 items-center gap-1">
                    <code
                        data-testid="share-url"
                        className="truncate rounded bg-muted px-1.5 py-0.5 text-xs"
                    >
                        {url}
                    </code>
                    {!revoked ? (
                        <CopyButton
                            value={url}
                            label={t('share.copy', 'Copy link')}
                        />
                    ) : null}
                </div>
                <p className="text-xs text-muted-foreground">
                    {revoked
                        ? t('share.revoked_on', 'Revoked on {{date}}', {
                              date: date(share.revokedAt),
                          })
                        : share.expiresAt
                          ? t('share.until', 'Until {{date}}', {
                                date: date(share.expiresAt),
                            })
                          : t('share.no_expiration', 'No expiration')}
                    {' · '}
                    {t('share.created_on', 'Created on {{date}}', {
                        date: date(share.createdAt),
                    })}
                </p>
            </div>
            <div className="flex shrink-0 gap-1">
                {!revoked ? (
                    <>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('common.edit', 'Edit')}
                            data-testid="share-edit"
                            onClick={edit}
                        >
                            <PencilIcon />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('share.revoke.confirm', 'Revoke')}
                            data-testid="share-revoke"
                            onClick={revoke}
                        >
                            <BanIcon />
                        </Button>
                    </>
                ) : null}
                {share.status !== ShareStatus.Active ? (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('common.delete', 'Delete')}
                        data-testid="share-delete"
                        onClick={remove}
                    >
                        <Trash2Icon />
                    </Button>
                ) : null}
            </div>
        </li>
    );
}

function ShareStatusBadge({status}: {status: ShareStatus}) {
    const {t} = useTranslation();

    switch (status) {
        case ShareStatus.Active:
            return (
                <Badge variant="success">
                    {t('share.status.active', 'Active')}
                </Badge>
            );
        case ShareStatus.Expired:
            return (
                <Badge variant="warning">
                    {t('share.status.expired', 'Expired')}
                </Badge>
            );
        case ShareStatus.Revoked:
            return (
                <Badge variant="muted">
                    {t('share.status.revoked', 'Revoked')}
                </Badge>
            );
    }
}

/** Renames a link or changes its expiration (extends an expired one) */
function ShareEditDialog({
    open,
    onOpenChange,
    resolve,
    share,
}: ModalProps<PublicationShare> & {share: PublicationShare}) {
    const {t} = useTranslation();
    const initialExpiresAt = toLocalInput(share.expiresAt);
    const [name, setName] = useState(share.name ?? '');
    const [expiresAt, setExpiresAt] = useState(initialExpiresAt);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('share.edit.title', 'Edit share link')}
            bodyClassName="space-y-4"
            onSubmit={async () => {
                const updated = await updatePublicationShare(share.id, {
                    name: name.trim() || null,
                    // Sent once changed: the input drops the seconds, and the
                    // API wants a new expiration in the future
                    ...(expiresAt !== initialExpiresAt
                        ? {expiresAt: fromLocalInput(expiresAt)}
                        : {}),
                });
                toast.success(t('share.edit.done', 'Share link saved'));
                resolve?.(updated);
            }}
        >
            <div>
                <Label htmlFor="share-edit-name" className="mb-1.5">
                    {t('share.field.name', 'Name')}
                </Label>
                <Input
                    id="share-edit-name"
                    value={name}
                    maxLength={255}
                    onChange={e => setName(e.target.value)}
                />
            </div>
            <ExpirationInput
                id="share-edit-expires-at"
                value={expiresAt}
                onChange={setExpiresAt}
            />
        </FormDialog>
    );
}

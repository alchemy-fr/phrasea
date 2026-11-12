'use client';

import {useState} from 'react';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
    CopyIcon,
    PauseIcon,
    PencilIcon,
    PlayIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert, Badge, EmptyState} from '@alchemy/next-ui/ui/misc';
import {FieldError, FormRow, Input} from '@alchemy/next-ui/ui/input';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {useConfirm} from '@alchemy/next-ui/ui/confirm';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import {
    useModals,
    type ModalProps,
} from '@alchemy/next-ui/modals/ModalProvider';
import {getErrorMessage, isApiError} from '@alchemy/next-ui/utils/errors';
import {copyToClipboard} from '@alchemy/next-ui/utils/misc';
import {Page} from '@/components/layout/AppShell';
import {
    createUploadLink,
    deleteUploadLink,
    getUploadLinks,
    getUploadLinkUrl,
    updateUploadLink,
} from '@/lib/api/uploadLinks';
import {formatFileSize} from '@/lib/utils/format';
import type {UploadLink, UploadLinkStatus} from '@/types/api';
import {RequirePermission} from './RequirePermission';
import {TargetSelect} from './TargetSelect';
import {violationsByField} from './violations';
import {
    initialFormValues,
    toInput,
    type SizeUnit,
    type UploadLinkFormValues,
} from './uploadLinkForm';

export const uploadLinksQueryKey = ['upload-links'] as const;

function linkUrl(link: Pick<UploadLink, 'token'>): string {
    return getUploadLinkUrl(link.token, window.location.origin);
}

function useCopyLink() {
    const {t} = useTranslation();

    return async (link: Pick<UploadLink, 'token'>) => {
        try {
            await copyToClipboard(linkUrl(link));
            toast.success(t('upload_links.copied', 'Link copied'));
        } catch {
            toast.error(
                t('upload_links.copy_failed', 'The link could not be copied')
            );
        }
    };
}

export function UploadLinksScreen() {
    return (
        <RequirePermission permission="upload_link">
            <UploadLinks />
        </RequirePermission>
    );
}

function UploadLinks() {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {data, error, isPending} = useQuery({
        queryKey: uploadLinksQueryKey,
        queryFn: () => getUploadLinks(),
    });

    const create = async () => {
        const created = await openModal(UploadLinkDialog, {});
        if (created) {
            openModal(UploadLinkCreatedDialog, {link: created});
        }
    };

    return (
        <Page
            title={t('upload_links.title', 'Upload links')}
            actions={
                <Button data-testid="upload-link-create" onClick={create}>
                    <PlusIcon /> {t('upload_links.create', 'New link')}
                </Button>
            }
            wide
        >
            <p className="-mt-4 mb-6 text-sm text-muted-foreground">
                {t(
                    'upload_links.intro',
                    'Anyone holding an upload link can send files to its target without an account, within its limits. What is sent is attributed to you.'
                )}
            </p>
            {isPending ? (
                <FullPageLoader />
            ) : error ? (
                <Alert variant="destructive">{getErrorMessage(error)}</Alert>
            ) : data.length === 0 ? (
                <EmptyState
                    testId="upload-links-empty"
                    title={t('upload_links.empty', 'No upload link yet.')}
                />
            ) : (
                <ul
                    data-testid="upload-links"
                    className="divide-y rounded-lg border"
                >
                    {data.map(link => (
                        <UploadLinkRow key={link.id} link={link} />
                    ))}
                </ul>
            )}
        </Page>
    );
}

function StatusBadge({status}: {status: UploadLinkStatus}) {
    const {t} = useTranslation();
    const labels: Record<UploadLinkStatus, string> = {
        active: t('upload_links.status.active', 'Active'),
        disabled: t('upload_links.status.disabled', 'Disabled'),
        expired: t('upload_links.status.expired', 'Expired'),
        exhausted: t('upload_links.status.exhausted', 'Limit reached'),
    };

    return (
        <Badge
            data-testid="upload-link-status"
            data-status={status}
            variant={
                status === 'active'
                    ? 'success'
                    : status === 'disabled'
                      ? 'muted'
                      : 'warning'
            }
        >
            {labels[status]}
        </Badge>
    );
}

function UploadLinkRow({link}: {link: UploadLink}) {
    const {t, i18n} = useTranslation();
    const {openModal} = useModals();
    const confirm = useConfirm();
    const copy = useCopyLink();
    const queryClient = useQueryClient();
    const refresh = () =>
        queryClient.invalidateQueries({queryKey: uploadLinksQueryKey});
    const formatDate = (iso: string) =>
        new Date(iso).toLocaleString(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });

    const toggle = useMutation({
        mutationFn: () => updateUploadLink(link.id, {enabled: !link.enabled}),
        onSuccess: refresh,
        onError: e => toast.error(getErrorMessage(e)),
    });
    const remove = async () => {
        if (
            await confirm({
                title: t('upload_links.delete_title', 'Delete the link'),
                description: t(
                    'upload_links.delete_confirm',
                    'Nothing more can be sent with it. What was already sent stays.'
                ),
                confirmLabel: t('upload_links.delete', 'Delete'),
                destructive: true,
            })
        ) {
            try {
                await deleteUploadLink(link.id);
                await refresh();
            } catch (e) {
                toast.error(getErrorMessage(e));
            }
        }
    };

    const assets =
        typeof link.maxAssets === 'number'
            ? `${link.usedAssets} / ${link.maxAssets}`
            : String(link.usedAssets);
    const size =
        typeof link.maxTotalSize === 'number'
            ? `${formatFileSize(link.usedSize)} / ${formatFileSize(link.maxTotalSize)}`
            : formatFileSize(link.usedSize);

    return (
        <li
            data-testid="upload-link-row"
            data-id={link.id}
            className="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3"
        >
            <div className="flex min-w-48 flex-1 flex-col gap-0.5">
                <div className="flex items-center gap-2">
                    <span
                        className="truncate font-medium"
                        data-testid="upload-link-name"
                    >
                        {link.name ||
                            t('upload_links.untitled', 'Untitled link')}
                    </span>
                    <StatusBadge status={link.status} />
                </div>
                <span className="truncate text-sm text-muted-foreground">
                    {link.target.name}
                </span>
            </div>
            <dl className="grid grid-cols-2 gap-x-4 gap-y-0.5 text-xs text-muted-foreground sm:grid-cols-4">
                <dt>{t('upload_links.files', 'Files')}</dt>
                <dd data-testid="upload-link-assets">{assets}</dd>
                <dt>{t('upload_links.size', 'Size')}</dt>
                <dd>{size}</dd>
                <dt>{t('upload_links.expires', 'Expires')}</dt>
                <dd>
                    {link.expiresAt
                        ? formatDate(link.expiresAt)
                        : t('upload_links.never', 'Never')}
                </dd>
                <dt>{t('upload_links.last_commit', 'Last upload')}</dt>
                <dd>
                    {link.lastCommitAt
                        ? formatDate(link.lastCommitAt)
                        : t('upload_links.none', 'None')}
                </dd>
            </dl>
            <div className="flex items-center gap-1">
                <Button
                    size="sm"
                    variant="outline"
                    data-testid="upload-link-copy"
                    onClick={() => copy(link)}
                >
                    <CopyIcon /> {t('upload_links.copy', 'Copy the link')}
                </Button>
                <Button
                    size="icon-sm"
                    variant="ghost"
                    data-testid="upload-link-toggle"
                    aria-label={
                        link.enabled
                            ? t('upload_links.disable', 'Disable')
                            : t('upload_links.enable', 'Enable')
                    }
                    loading={toggle.isPending}
                    onClick={() => toggle.mutate()}
                >
                    {link.enabled ? <PauseIcon /> : <PlayIcon />}
                </Button>
                <Button
                    size="icon-sm"
                    variant="ghost"
                    data-testid="upload-link-edit"
                    aria-label={t('upload_links.edit', 'Edit')}
                    onClick={() => openModal(UploadLinkDialog, {link})}
                >
                    <PencilIcon />
                </Button>
                <Button
                    size="icon-sm"
                    variant="ghost"
                    data-testid="upload-link-delete"
                    aria-label={t('upload_links.delete', 'Delete')}
                    onClick={remove}
                >
                    <Trash2Icon />
                </Button>
            </div>
        </li>
    );
}

/** Creates a link, or edits one (its target stays) */
function UploadLinkDialog({
    open,
    onOpenChange,
    resolve,
    link,
}: ModalProps<UploadLink> & {link?: UploadLink}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const creating = !link;
    const [values, setValues] = useState<UploadLinkFormValues>(() =>
        initialFormValues(link)
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const set = (patch: Partial<UploadLinkFormValues>) =>
        setValues(v => ({...v, ...patch}));

    const submit = async () => {
        setErrors({});
        try {
            const input = toInput(values, creating);
            const saved = creating
                ? await createUploadLink(input)
                : await updateUploadLink(link.id, input);
            await queryClient.invalidateQueries({
                queryKey: uploadLinksQueryKey,
            });
            resolve?.(saved);

            return false;
        } catch (e) {
            if (isApiError(e, 422)) {
                setErrors(violationsByField(e));

                return false;
            }
            throw e;
        }
    };

    const unitOptions: {value: SizeUnit; label: string}[] = [
        {value: 'MB', label: t('upload_links.unit.mb', 'MB')},
        {value: 'GB', label: t('upload_links.unit.gb', 'GB')},
    ];

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={
                creating
                    ? t('upload_links.create_title', 'New upload link')
                    : t('upload_links.edit_title', 'Edit the upload link')
            }
            submitLabel={
                creating
                    ? t('upload_links.create_submit', 'Create the link')
                    : undefined
            }
            canSubmit={!!values.target}
            onSubmit={submit}
        >
            <div data-testid="upload-link-form">
                {creating ? (
                    <FormRow
                        label={t('upload_links.target', 'Target')}
                        htmlFor="upload-link-target"
                        error={errors.target}
                    >
                        <TargetSelect
                            id="upload-link-target"
                            value={values.target}
                            onChange={target => set({target})}
                        />
                    </FormRow>
                ) : null}
                <FormRow
                    label={t('upload_links.name', 'Name')}
                    htmlFor="upload-link-name"
                    help={t(
                        'upload_links.name_help',
                        'Who the link is for, to find it again.'
                    )}
                    error={errors.name}
                >
                    <Input
                        id="upload-link-name"
                        data-testid="upload-link-name-input"
                        value={values.name}
                        onChange={e => set({name: e.target.value})}
                    />
                </FormRow>
                <FormRow
                    label={t('upload_links.expires_at', 'Expiration')}
                    htmlFor="upload-link-expires"
                    help={t(
                        'upload_links.expires_help',
                        'Leave empty for a link that never expires.'
                    )}
                    error={errors.expiresAt}
                >
                    <Input
                        id="upload-link-expires"
                        data-testid="upload-link-expires-input"
                        type="datetime-local"
                        value={values.expiresAt}
                        onChange={e => set({expiresAt: e.target.value})}
                    />
                </FormRow>
                <FormRow
                    label={t(
                        'upload_links.max_assets',
                        'Maximum number of files'
                    )}
                    htmlFor="upload-link-max-assets"
                    help={t(
                        'upload_links.limit_help',
                        'Over every upload made with the link. Leave empty for no limit.'
                    )}
                    error={errors.maxAssets}
                >
                    <Input
                        id="upload-link-max-assets"
                        data-testid="upload-link-max-assets-input"
                        type="number"
                        min={1}
                        step={1}
                        value={values.maxAssets}
                        onChange={e => set({maxAssets: e.target.value})}
                    />
                </FormRow>
                <FormRow
                    label={t(
                        'upload_links.max_total_size',
                        'Maximum total size'
                    )}
                    htmlFor="upload-link-max-size"
                    help={t(
                        'upload_links.limit_help',
                        'Over every upload made with the link. Leave empty for no limit.'
                    )}
                    error={errors.maxTotalSize}
                >
                    <div className="flex gap-2">
                        <Input
                            id="upload-link-max-size"
                            data-testid="upload-link-max-size-input"
                            type="number"
                            min={0}
                            step="any"
                            value={values.maxTotalSize}
                            onChange={e => set({maxTotalSize: e.target.value})}
                        />
                        <div className="w-24 shrink-0">
                            <SimpleSelect
                                value={values.sizeUnit}
                                onValueChange={sizeUnit => set({sizeUnit})}
                                options={unitOptions}
                            />
                        </div>
                    </div>
                </FormRow>
                {errors[''] ? <FieldError>{errors['']}</FieldError> : null}
            </div>
        </FormDialog>
    );
}

/** The URL of a link just created, to hand out */
function UploadLinkCreatedDialog({
    open,
    onOpenChange,
    link,
}: ModalProps & {link: UploadLink}) {
    const {t} = useTranslation();
    const copy = useCopyLink();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('upload_links.created_title', 'The link is ready')}
            description={t(
                'upload_links.created_description',
                'Send it to whoever must upload: anyone holding it can send files to {{target}}.',
                {target: link.target.name}
            )}
            hideCancel
            submitLabel={t('common.close', 'Close')}
        >
            <div className="flex gap-2">
                <Input
                    readOnly
                    data-testid="upload-link-created-url"
                    value={linkUrl(link)}
                    onFocus={e => e.target.select()}
                />
                <Button
                    type="button"
                    variant="outline"
                    data-testid="upload-link-created-copy"
                    onClick={() => copy(link)}
                >
                    <CopyIcon /> {t('upload_links.copy', 'Copy the link')}
                </Button>
            </div>
        </FormDialog>
    );
}

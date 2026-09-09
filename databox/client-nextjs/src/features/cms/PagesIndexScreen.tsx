'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {useInfiniteQuery, useQueryClient} from '@tanstack/react-query';
import {
    ExternalLinkIcon,
    FileTextIcon,
    PencilIcon,
    PlusIcon,
    Trash2Icon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {CmsPage} from '@/types/api';
import {deletePage, getPages, postPage, putPage} from '@/lib/api/misc';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {Button} from '@/components/ui/button';
import {Badge, EmptyState, Skeleton} from '@/components/ui/misc';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {useModals, type ModalProps} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {routes} from '@/lib/routes';
import {formatDateTime} from '@/lib/utils/format';

export function PagesIndexScreen() {
    const {t, i18n} = useTranslation();
    const router = useRouter();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const pages = useInfiniteQuery({
        queryKey: ['pages'],
        queryFn: ({pageParam}) => getPages(pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const items = pages.data?.pages.flatMap(p => p.items) ?? [];

    return (
        <RequireAuth>
            <div className="mx-auto w-full max-w-4xl space-y-4 overflow-y-auto p-4">
                <div className="flex items-center gap-2">
                    <h1 className="flex-1 text-lg font-semibold">
                        {t('cms.pages', 'Pages')}{' '}
                        <Badge variant="warning">BETA</Badge>
                    </h1>
                    <Button
                        size="sm"
                        onClick={() =>
                            openModal(PageFormDialog, {
                                onSaved: p =>
                                    router.push(routes.pageEdit(p.id)),
                            })
                        }
                    >
                        <PlusIcon /> {t('cms.create', 'Create page')}
                    </Button>
                </div>
                {pages.isLoading
                    ? [...Array(3)].map((_, i) => (
                          <Skeleton key={i} className="h-14" />
                      ))
                    : null}
                {items.length === 0 && !pages.isLoading ? (
                    <EmptyState
                        icon={<FileTextIcon />}
                        title={t('cms.empty', 'No page yet')}
                    />
                ) : null}
                <ul className="space-y-2">
                    {items.map(p => (
                        <li
                            key={p.id}
                            className="flex items-center gap-3 rounded-md border bg-card p-3 text-sm"
                        >
                            <FileTextIcon className="size-5 text-muted-foreground" />
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2 font-medium">
                                    {p.title}
                                    {!p.enabled ? (
                                        <Badge variant="muted">
                                            {t('common.disabled', 'disabled')}
                                        </Badge>
                                    ) : null}
                                    {p.public ? (
                                        <Badge variant="success">
                                            {t('common.public', 'Public')}
                                        </Badge>
                                    ) : null}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    /p/{p.slug || '(home)'} ·{' '}
                                    {formatDateTime(
                                        p.updatedAt,
                                        'medium',
                                        i18n.language
                                    )}
                                </div>
                            </div>
                            <Button variant="ghost" size="icon-sm" asChild>
                                <a
                                    href={p.slug ? routes.page(p.slug) : '/'}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLinkIcon />
                                </a>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() =>
                                    router.push(routes.pageEdit(p.id))
                                }
                            >
                                <PencilIcon />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                className="text-destructive"
                                onClick={() =>
                                    openModal(ConfirmDialog, {
                                        title: t(
                                            'cms.delete.title',
                                            'Delete page "{{title}}"?',
                                            {title: p.title}
                                        ),
                                        destructive: true,
                                        textToType: p.title,
                                        onConfirm: async () => {
                                            await deletePage(p.id);
                                            void queryClient.invalidateQueries({
                                                queryKey: ['pages'],
                                            });
                                        },
                                    })
                                }
                            >
                                <Trash2Icon />
                            </Button>
                        </li>
                    ))}
                </ul>
                {pages.hasNextPage ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => pages.fetchNextPage()}
                        loading={pages.isFetchingNextPage}
                    >
                        {t('common.load_more', 'Load more')}
                    </Button>
                ) : null}
            </div>
        </RequireAuth>
    );
}

export function PageFormDialog({
    open,
    onOpenChange,
    page,
    onSaved,
}: ModalProps & {page?: CmsPage; onSaved?: (page: CmsPage) => void}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [title, setTitle] = useState(page?.title ?? '');
    const [slug, setSlug] = useState(page?.slug ?? '');
    const [description, setDescription] = useState(page?.description ?? '');
    const [enabled, setEnabled] = useState(page?.enabled ?? true);
    const [isPublic, setIsPublic] = useState(page?.public ?? false);
    const [loading, setLoading] = useState(false);

    const submit = async () => {
        setLoading(true);
        try {
            const data = {title, slug, description, enabled, public: isPublic};
            const saved = page
                ? await putPage(page.id, data)
                : await postPage({...data, data: {type: 'doc', content: []}});
            void queryClient.invalidateQueries({queryKey: ['pages']});
            toast.success(t('cms.saved', 'Page saved'));
            onSaved?.(saved);
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {page
                            ? t('cms.edit_meta', 'Page settings')
                            : t('cms.create', 'Create page')}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody>
                    <FormRow label={t('cms.title', 'Title')}>
                        <Input
                            autoFocus
                            value={title}
                            onChange={e => setTitle(e.target.value)}
                        />
                    </FormRow>
                    <FormRow
                        label={t('cms.slug', 'Slug')}
                        help={t(
                            'cms.slug_help',
                            'Leave empty for the home page'
                        )}
                    >
                        <Input
                            value={slug}
                            onChange={e =>
                                setSlug(
                                    e.target.value
                                        .toLowerCase()
                                        .replace(/[^a-z0-9-_]/g, '-')
                                )
                            }
                            className="font-mono"
                        />
                    </FormRow>
                    <FormRow label={t('common.description', 'Description')}>
                        <Textarea
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                        />
                    </FormRow>
                    <div className="flex gap-6">
                        <LabeledControl label={t('common.enabled', 'Enabled')}>
                            <Switch
                                checked={enabled}
                                onCheckedChange={setEnabled}
                            />
                        </LabeledControl>
                        <LabeledControl label={t('common.public', 'Public')}>
                            <Switch
                                checked={isPublic}
                                onCheckedChange={setIsPublic}
                            />
                        </LabeledControl>
                    </div>
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={!title.trim()}
                        loading={loading}
                    >
                        {t('common.save', 'Save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

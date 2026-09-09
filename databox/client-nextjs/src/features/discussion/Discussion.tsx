'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery, useQueryClient} from '@tanstack/react-query';
import {
    MoreHorizontalIcon,
    PencilIcon,
    SendIcon,
    Trash2Icon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {ThreadMessage} from '@/types/api';
import {
    deleteMessage,
    getThreadMessages,
    postMessage,
    putMessage,
} from '@/lib/api/misc';
import {Button} from '@/components/ui/button';
import {Textarea} from '@/components/ui/input';
import {Avatar, Skeleton} from '@/components/ui/misc';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {Tooltip} from '@/components/ui/overlays';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {formatDateTime} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';
import {MentionTextarea} from './MentionTextarea';
import {FormattedMessage} from './FormattedMessage';

/**
 * Comment thread attached to an entity (asset). Realtime updates through the
 * `thread-{key}` channel; `#discussion-{id}` in the URL highlights a message.
 */
export function Discussion({
    threadKey,
    threadId: initialThreadId,
}: {
    threadKey: string;
    threadId?: string;
}) {
    const {t, i18n} = useTranslation();
    const queryClient = useQueryClient();
    const {openModal} = useModals();
    const [threadId, setThreadId] = useState(initialThreadId);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [editing, setEditing] = useState<{
        id: string;
        content: string;
    } | null>(null);
    const [selected, setSelected] = useState<string>();
    const listRef = useRef<HTMLDivElement>(null);
    const queryKey = ['thread', threadId];

    const messages = useInfiniteQuery({
        queryKey,
        queryFn: ({pageParam}) => getThreadMessages(threadId!, pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: !!threadId,
    });
    const items = messages.data?.pages.flatMap(p => p.items) ?? [];

    const upsertLocal = (message: ThreadMessage) => {
        queryClient.setQueryData(queryKey, (prev: typeof messages.data) => {
            if (!prev) {
                return {
                    pages: [{items: [message], total: 1}],
                    pageParams: [undefined],
                };
            }
            const exists = prev.pages.some(p =>
                p.items.some(m => m.id === message.id)
            );
            if (exists) {
                return {
                    ...prev,
                    pages: prev.pages.map(p => ({
                        ...p,
                        items: p.items.map(m =>
                            m.id === message.id ? message : m
                        ),
                    })),
                };
            }
            const pages = [...prev.pages];
            pages[pages.length - 1] = {
                ...pages[pages.length - 1],
                items: [...pages[pages.length - 1].items, message],
            };

            return {...prev, pages};
        });
    };
    const removeLocal = (id: string) =>
        queryClient.setQueryData(queryKey, (prev: typeof messages.data) =>
            prev
                ? {
                      ...prev,
                      pages: prev.pages.map(p => ({
                          ...p,
                          items: p.items.filter(m => m.id !== id),
                      })),
                  }
                : prev
        );

    useChannelEvent(`thread-${threadKey}`, 'message', (m: ThreadMessage) =>
        upsertLocal(m)
    );
    useChannelEvent(
        `thread-${threadKey}`,
        'message-delete',
        (m: {id: string}) => removeLocal(m.id)
    );

    useEffect(() => {
        const prefix = '#discussion-';
        if (window.location.hash.startsWith(prefix)) {
            const id = window.location.hash.slice(prefix.length);
            setSelected(id);
            setTimeout(
                () =>
                    listRef.current
                        ?.querySelector(`[data-message-id="${id}"]`)
                        ?.scrollIntoView({behavior: 'smooth', block: 'center'}),
                500
            );
        }
    }, [items.length]);

    const send = async () => {
        if (!draft.trim()) {
            return;
        }
        setSending(true);
        try {
            const message = await postMessage({
                threadKey,
                threadId,
                content: draft.trim(),
            });
            setDraft('');
            if (!threadId) {
                // thread created lazily on the first message: reload from the asset
                setThreadId((message as any).thread?.id ?? threadId);
            }
            upsertLocal(message);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSending(false);
        }
    };

    const saveEdit = async () => {
        if (!editing) {
            return;
        }
        const m = await putMessage(editing.id, {content: editing.content});
        upsertLocal(m);
        setEditing(null);
    };

    return (
        <div className="space-y-3">
            <div ref={listRef} className="space-y-3">
                {messages.hasNextPage ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-full"
                        onClick={() => messages.fetchNextPage()}
                        loading={messages.isFetchingNextPage}
                    >
                        {t('discussion.load_more', 'Load earlier messages')}
                    </Button>
                ) : null}
                {messages.isLoading
                    ? [...Array(2)].map((_, i) => (
                          <Skeleton key={i} className="h-14" />
                      ))
                    : null}
                {threadId && items.length === 0 && !messages.isLoading ? (
                    <p className="text-sm text-muted-foreground">
                        {t('discussion.empty', 'No message yet')}
                    </p>
                ) : null}
                {items.map(m => (
                    <div
                        key={m.id}
                        data-message-id={m.id}
                        className={cn(
                            'group/msg flex gap-2 rounded-md p-1.5 transition-colors',
                            selected === m.id &&
                                'bg-success/15 ring-1 ring-success'
                        )}
                    >
                        <Avatar name={m.author.username} size="sm" />
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2 text-xs">
                                <span className="font-medium">
                                    {m.author.username}
                                </span>
                                <Tooltip
                                    content={formatDateTime(
                                        m.createdAt,
                                        'long',
                                        i18n.language
                                    )}
                                >
                                    <span className="text-muted-foreground">
                                        {formatDateTime(
                                            m.createdAt,
                                            'relative',
                                            i18n.language
                                        )}
                                    </span>
                                </Tooltip>
                                {m.capabilities?.edit ||
                                m.capabilities?.delete ? (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon-xs"
                                                className="ml-auto opacity-0 group-hover/msg:opacity-100 data-[state=open]:opacity-100"
                                            >
                                                <MoreHorizontalIcon />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            {m.capabilities.edit ? (
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setEditing({
                                                            id: m.id,
                                                            content: m.content,
                                                        })
                                                    }
                                                >
                                                    <PencilIcon />{' '}
                                                    {t('common.edit', 'Edit')}
                                                </DropdownMenuItem>
                                            ) : null}
                                            {m.capabilities.delete ? (
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onSelect={() =>
                                                        openModal(
                                                            ConfirmDialog,
                                                            {
                                                                title: t(
                                                                    'discussion.delete.title',
                                                                    'Delete this message?'
                                                                ),
                                                                destructive: true,
                                                                onConfirm:
                                                                    async () => {
                                                                        await deleteMessage(
                                                                            m.id
                                                                        );
                                                                        removeLocal(
                                                                            m.id
                                                                        );
                                                                    },
                                                            }
                                                        )
                                                    }
                                                >
                                                    <Trash2Icon />{' '}
                                                    {t(
                                                        'common.delete',
                                                        'Delete'
                                                    )}
                                                </DropdownMenuItem>
                                            ) : null}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                ) : null}
                            </div>
                            {editing?.id === m.id ? (
                                <div className="mt-1 space-y-1">
                                    <Textarea
                                        value={editing.content}
                                        onChange={e =>
                                            setEditing({
                                                id: m.id,
                                                content: e.target.value,
                                            })
                                        }
                                        className="min-h-16 text-sm"
                                        autoFocus
                                    />
                                    <div className="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setEditing(null)}
                                        >
                                            {t('common.cancel', 'Cancel')}
                                        </Button>
                                        <Button size="sm" onClick={saveEdit}>
                                            {t('common.save', 'Save')}
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-sm">
                                    <FormattedMessage content={m.content} />
                                </div>
                            )}
                        </div>
                    </div>
                ))}
            </div>
            <div className="space-y-2">
                <MentionTextarea
                    value={draft}
                    onChange={setDraft}
                    placeholder={t(
                        'discussion.placeholder',
                        'Write a message… (@ to mention, Ctrl+Enter to send)'
                    )}
                    onSubmit={send}
                />
                <div className="flex justify-end">
                    <Button
                        size="sm"
                        onClick={send}
                        disabled={!draft.trim()}
                        loading={sending}
                    >
                        <SendIcon /> {t('discussion.send', 'Send')}
                    </Button>
                </div>
            </div>
        </div>
    );
}

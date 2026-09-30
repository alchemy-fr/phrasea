'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery, useQueryClient} from '@tanstack/react-query';
import {
    MoreHorizontalIcon,
    PencilIcon,
    ReplyIcon,
    Trash2Icon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {ThreadMessage} from '@/types/api';
import {
    deleteMessage,
    getMessage,
    getThreadMessages,
    postMessage,
    putMessage,
} from '@/lib/api/misc';
import {Button} from '@/components/ui/button';
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
import {useUnsavedChangesPrompt} from '@/lib/navigation/unsavedChanges';
import {useAuth} from '@/lib/auth/AuthProvider';
import {FormattedMessage} from './FormattedMessage';
import {MessageComposer, MessageComposerHandle} from './MessageComposer';
import {PostedAttachments} from './MessageAttachments';
import type {FileAttachmentInput} from './messageAttachments';

/** Message hover actions (always shown on touch screens) */
const messageActionClass =
    'opacity-0 group-hover/msg:opacity-100 focus-visible:opacity-100 data-[state=open]:opacity-100 pointer-coarse:opacity-100';

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
    const {user} = useAuth();
    const [threadId, setThreadId] = useState(initialThreadId);
    /** The message being written, as stored (see `messageMarkup`) */
    const [draft, setDraft] = useState('');
    const [attachmentCount, setAttachmentCount] = useState(0);
    const [sending, setSending] = useState(false);
    const [editing, setEditing] = useState<{
        id: string;
        content: string;
    } | null>(null);
    const [savingEdit, setSavingEdit] = useState(false);
    const [selected, setSelected] = useState<string>();
    const listRef = useRef<HTMLDivElement>(null);
    const composerRef = useRef<MessageComposerHandle>(null);
    const queryKey = ['thread', threadId];

    const messages = useInfiniteQuery({
        queryKey,
        queryFn: ({pageParam}) => getThreadMessages(threadId!, pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: !!threadId,
    });
    const items = messages.data?.pages.flatMap(p => p.items) ?? [];

    // A message being written (or edited) must not be lost when leaving
    useUnsavedChangesPrompt(
        !!draft ||
            attachmentCount > 0 ||
            (!!editing &&
                items.find(m => m.id === editing.id)?.content !==
                    editing.content)
    );

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

    // The event only carries the id: the message itself is read back with the
    // recipient's own permissions.
    useChannelEvent(`thread-${threadKey}`, 'message', ({id}: {id: string}) => {
        void getMessage(id).then(upsertLocal);
    });
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

    const send = async (
        content: string,
        attachments: FileAttachmentInput[]
    ) => {
        if ((!content && attachments.length === 0) || sending) {
            return;
        }
        setSending(true);
        try {
            const message = await postMessage({
                threadKey,
                threadId,
                content,
                attachments: attachments.length > 0 ? attachments : undefined,
            });
            composerRef.current?.clear();
            if (!threadId) {
                // The thread is created lazily by the first message: the
                // query of the new id loads it (refetching the current one
                // would read `/threads/undefined/messages`)
                setThreadId((message as any).thread?.id);

                return;
            }
            upsertLocal(message);
            // The creation payload is partial: reload the thread
            void messages.refetch();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSending(false);
        }
    };

    const saveEdit = async (content: string) => {
        if (!editing || !content || savingEdit) {
            return;
        }
        setSavingEdit(true);
        try {
            const m = await putMessage(editing.id, {content});
            upsertLocal(m);
            setEditing(null);
            // The update payload is partial: reload the thread
            void messages.refetch();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSavingEdit(false);
        }
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
                                'bg-success/15 ring-1 ring-success',
                            editing?.id === m.id &&
                                'bg-primary/10 ring-1 ring-primary/40'
                        )}
                    >
                        <Avatar name={m.author?.username ?? '?'} size="sm" />
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2 text-xs">
                                <span className="font-medium">
                                    {m.author?.username ?? '?'}
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
                                <MessageActions
                                    message={m}
                                    onReply={() => {
                                        // Replying to oneself quotes the
                                        // message, to others mentions them
                                        if (user && m.author?.id === user.id) {
                                            composerRef.current?.quote(
                                                m.content
                                            );
                                        } else if (m.author) {
                                            composerRef.current?.mention(
                                                m.author
                                            );
                                        }
                                    }}
                                    onEdit={() =>
                                        setEditing({
                                            id: m.id,
                                            content: m.content,
                                        })
                                    }
                                    onDelete={() =>
                                        openModal(ConfirmDialog, {
                                            title: t(
                                                'discussion.delete.title',
                                                'Delete this message?'
                                            ),
                                            destructive: true,
                                            onConfirm: async () => {
                                                await deleteMessage(m.id);
                                                removeLocal(m.id);
                                                setEditing(e =>
                                                    e?.id === m.id ? null : e
                                                );
                                            },
                                        })
                                    }
                                />
                            </div>
                            <div className="text-sm">
                                <FormattedMessage
                                    content={m.content}
                                    currentUserId={user?.id}
                                />
                                <PostedAttachments
                                    attachments={m.attachments}
                                />
                            </div>
                        </div>
                    </div>
                ))}
            </div>
            <MessageComposer
                ref={composerRef}
                onChange={content =>
                    editing
                        ? setEditing(e => (e ? {...e, content} : e))
                        : setDraft(content)
                }
                onAttachmentsChange={setAttachmentCount}
                onSubmit={send}
                editing={editing}
                onSaveEdit={saveEdit}
                onCancelEdit={() => setEditing(null)}
                sending={sending || savingEdit}
                placeholder={t('discussion.placeholder', 'Write a message…')}
            />
        </div>
    );
}

function MessageActions({
    message,
    onReply,
    onEdit,
    onDelete,
}: {
    message: ThreadMessage;
    onReply: () => void;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const {t} = useTranslation();
    const {capabilities} = message;

    return (
        <div className="ml-auto flex items-center">
            <Tooltip content={t('discussion.reply', 'Reply')}>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    aria-label={t('discussion.reply', 'Reply')}
                    className={messageActionClass}
                    onClick={onReply}
                >
                    <ReplyIcon />
                </Button>
            </Tooltip>
            {capabilities?.edit || capabilities?.delete ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            className={messageActionClass}
                        >
                            <MoreHorizontalIcon />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {capabilities.edit ? (
                            <DropdownMenuItem onSelect={onEdit}>
                                <PencilIcon /> {t('common.edit', 'Edit')}
                            </DropdownMenuItem>
                        ) : null}
                        {capabilities.delete ? (
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={onDelete}
                            >
                                <Trash2Icon /> {t('common.delete', 'Delete')}
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : null}
        </div>
    );
}

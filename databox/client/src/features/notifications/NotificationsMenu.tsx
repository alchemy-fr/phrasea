'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {
    useInfiniteQuery,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {BellIcon, CheckCheckIcon} from 'lucide-react';
import {toast} from 'sonner';
import {Button} from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/overlays';
import {api} from '@/lib/api/http';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {formatDateTime} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';
import {resolveNotificationUri} from './NotificationUriListener';
import {htmlToText} from './notificationText';

type Notification = {
    id: string;
    subject?: string | null;
    content?: string | null;
    data: {uri?: string};
    read: boolean;
    createdAt?: string | null;
};

type ListResponse = {
    items: Notification[];
    total: number;
    page: number;
    limit: number;
    unreadCount: number;
};

const PAGE_SIZE = 20;

export function NotificationsMenu() {
    const {t, i18n} = useTranslation();
    const {user} = useAuth();
    const router = useRouter();
    const queryClient = useQueryClient();
    const [open, setOpen] = useState(false);

    const unread = useQuery({
        queryKey: ['notifications', 'unread-count'],
        queryFn: () =>
            api.get<{unreadCount: number}>('/notifications/unread-count'),
        refetchInterval: 60_000,
        select: r => r.unreadCount,
    });

    const list = useInfiniteQuery({
        queryKey: ['notifications', 'list'],
        queryFn: ({pageParam}) =>
            api.get<ListResponse>('/notifications', {
                params: {limit: PAGE_SIZE, page: pageParam},
            }),
        initialPageParam: 1,
        // The endpoint pages by number, without a link to the next page
        getNextPageParam: last =>
            last.page * last.limit < last.total ? last.page + 1 : undefined,
        enabled: open,
    });
    // A notification received meanwhile shifts the pages: listed once
    const notifications = [
        ...new Map(
            (list.data?.pages.flatMap(p => p.items) ?? []).map(n => [n.id, n])
        ).values(),
    ];

    const invalidate = () =>
        queryClient.invalidateQueries({queryKey: ['notifications']});

    useChannelEvent(
        user ? `private-user-${user.id}` : undefined,
        'notification',
        (data: Notification) => {
            toast(data.subject ?? t('notifications.new', 'New notification'), {
                description: data.content
                    ? htmlToText(data.content)
                    : undefined,
                action: data.data?.uri
                    ? {
                          label: t('common.open', 'Open'),
                          onClick: () =>
                              router.push(
                                  resolveNotificationUri(data.data.uri!)
                              ),
                      }
                    : undefined,
            });
            void invalidate();
        }
    );

    const markRead = async (n: Notification) => {
        if (!n.read) {
            await api.post(`/notifications/${n.id}/read`);
            void invalidate();
        }
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    className="relative"
                    aria-label={t('notifications.title', 'Notifications')}
                >
                    <BellIcon />
                    {unread.data ? (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold text-destructive-foreground">
                            {unread.data > 99 ? '99+' : unread.data}
                        </span>
                    ) : null}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-96 p-0">
                <div className="flex items-center justify-between border-b px-3 py-2">
                    <span className="font-medium">
                        {t('notifications.title', 'Notifications')}
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={!unread.data}
                        onClick={async () => {
                            await api.post('/notifications/read-all');
                            void invalidate();
                        }}
                    >
                        <CheckCheckIcon />{' '}
                        {t('notifications.mark_all_read', 'Mark all as read')}
                    </Button>
                </div>
                <div className="max-h-96 overflow-y-auto">
                    {notifications.length ? (
                        notifications.map(n => (
                            <button
                                key={n.id}
                                type="button"
                                className={cn(
                                    'flex w-full flex-col gap-0.5 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-accent',
                                    !n.read && 'bg-primary/5'
                                )}
                                onClick={async () => {
                                    await markRead(n);
                                    if (n.data?.uri) {
                                        setOpen(false);
                                        router.push(
                                            resolveNotificationUri(n.data.uri)
                                        );
                                    }
                                }}
                            >
                                <span
                                    className={cn(
                                        'font-medium',
                                        !n.read && 'text-primary'
                                    )}
                                >
                                    {n.subject}
                                </span>
                                {n.content ? (
                                    <span className="line-clamp-2 text-xs text-muted-foreground">
                                        {htmlToText(n.content)}
                                    </span>
                                ) : null}
                                <span className="text-[11px] text-muted-foreground">
                                    {formatDateTime(
                                        n.createdAt,
                                        'relative',
                                        i18n.language
                                    )}
                                </span>
                            </button>
                        ))
                    ) : (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            {list.isLoading
                                ? t('common.loading', 'Loading…')
                                : t('notifications.empty', 'No notification')}
                        </p>
                    )}
                    {list.hasNextPage ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="w-full"
                            loading={list.isFetchingNextPage}
                            onClick={() => list.fetchNextPage()}
                        >
                            {t('common.load_more', 'Load more')}
                        </Button>
                    ) : null}
                </div>
            </PopoverContent>
        </Popover>
    );
}

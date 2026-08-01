import React from 'react';
import type {HttpClient} from '@alchemy/api';
import {createNotificationApi} from './api';
import type {
    Notification,
    RealtimeNotification,
    RegisterNotificationRealtime,
} from './types';

type Options = {
    apiClient: HttpClient;
    userId: string;
    registerRealtime?: RegisterNotificationRealtime;
    realtimeChannelPrefix?: string;
    realtimeEvent?: string;
    pageSize?: number;
    /**
     * Whether the notification list is currently visible. The full list is only
     * loaded lazily when it first becomes active.
     */
    active: boolean;
};

type State = {
    items: Notification[];
    unreadCount: number;
    loading: boolean;
    loaded: boolean;
    page: number;
    total: number;
};

export type UseNotificationsResult = {
    items: Notification[];
    unreadCount: number;
    loading: boolean;
    loaded: boolean;
    hasMore: boolean;
    loadMore: () => void;
    markRead: (id: string) => void;
    markAllRead: () => void;
};

function realtimeToNotification(data: RealtimeNotification): Notification {
    return {
        id: data.id,
        subject: null,
        content: null,
        data: data.data ?? {},
        read: false,
        readAt: null,
        createdAt: data.createdAt ?? new Date().toISOString(),
    };
}

export function useNotifications({
    apiClient,
    userId,
    registerRealtime,
    realtimeChannelPrefix = 'private-user-',
    realtimeEvent = 'notification',
    pageSize = 20,
    active,
}: Options): UseNotificationsResult {
    const api = React.useMemo(
        () => createNotificationApi(apiClient),
        [apiClient]
    );

    const [state, setState] = React.useState<State>({
        items: [],
        unreadCount: 0,
        loading: false,
        loaded: false,
        page: 0,
        total: 0,
    });

    // Keep a ref to always read the freshest page inside async callbacks.
    const stateRef = React.useRef(state);
    stateRef.current = state;

    const loadingRef = React.useRef(false);

    const loadPage = React.useCallback(
        async (page: number, signal?: AbortSignal) => {
            if (loadingRef.current) {
                return;
            }
            loadingRef.current = true;
            setState(s => ({...s, loading: true}));

            try {
                const res = await api.list({page, limit: pageSize, signal});
                if (signal?.aborted) {
                    return;
                }

                setState(s => {
                    // Merge, de-duplicating on id (a realtime push may have
                    // already inserted an item that also appears in this page).
                    const known = new Set(s.items.map(i => i.id));
                    const merged =
                        page === 1
                            ? res.items
                            : [
                                  ...s.items,
                                  ...res.items.filter(i => !known.has(i.id)),
                              ];

                    return {
                        ...s,
                        items: merged,
                        unreadCount: res.unreadCount,
                        total: res.total,
                        page: res.page,
                        loading: false,
                        loaded: true,
                    };
                });
            } catch (e) {
                if (!signal?.aborted) {
                    setState(s => ({...s, loading: false}));
                }
                throw e;
            } finally {
                loadingRef.current = false;
            }
        },
        [api, pageSize]
    );

    // Initial unread-count fetch (independent of the list being opened).
    React.useEffect(() => {
        const controller = new AbortController();
        api.unreadCount(controller.signal)
            .then(unreadCount => {
                if (!controller.signal.aborted) {
                    setState(s => ({...s, unreadCount}));
                }
            })
            .catch(() => {
                /* silently ignore, badge simply stays hidden */
            });

        return () => controller.abort();
    }, [api]);

    // Lazily load the first page the first time the list becomes visible.
    React.useEffect(() => {
        if (!active || stateRef.current.loaded || loadingRef.current) {
            return;
        }
        const controller = new AbortController();
        loadPage(1, controller.signal).catch(() => {});

        return () => controller.abort();
    }, [active, loadPage]);

    // Real-time subscription: prepend new notifications and bump the counter.
    React.useEffect(() => {
        if (!registerRealtime || !userId) {
            return;
        }

        return registerRealtime(
            `${realtimeChannelPrefix}${userId}`,
            realtimeEvent,
            data => {
                setState(s => {
                    if (s.items.some(i => i.id === data.id)) {
                        return s;
                    }

                    return {
                        ...s,
                        items: [realtimeToNotification(data), ...s.items],
                        unreadCount: s.unreadCount + 1,
                        total: s.total + 1,
                    };
                });
            }
        );
    }, [registerRealtime, userId, realtimeChannelPrefix, realtimeEvent]);

    const loadMore = React.useCallback(() => {
        loadPage(stateRef.current.page + 1).catch(() => {});
    }, [loadPage]);

    const markRead = React.useCallback(
        (id: string) => {
            const target = stateRef.current.items.find(i => i.id === id);
            if (!target || target.read) {
                return;
            }

            // Optimistic update.
            setState(s => ({
                ...s,
                items: s.items.map(i =>
                    i.id === id
                        ? {...i, read: true, readAt: new Date().toISOString()}
                        : i
                ),
                unreadCount: Math.max(0, s.unreadCount - 1),
            }));

            api.markRead(id).catch(() => {
                // Roll back on failure.
                setState(s => ({
                    ...s,
                    items: s.items.map(i =>
                        i.id === id ? {...i, read: false, readAt: null} : i
                    ),
                    unreadCount: s.unreadCount + 1,
                }));
            });
        },
        [api]
    );

    const markAllRead = React.useCallback(() => {
        const previous = stateRef.current;
        if (previous.unreadCount === 0) {
            return;
        }
        const now = new Date().toISOString();

        setState(s => ({
            ...s,
            items: s.items.map(i =>
                i.read ? i : {...i, read: true, readAt: now}
            ),
            unreadCount: 0,
        }));

        api.markAllRead().catch(() => {
            setState(s => ({
                ...s,
                items: previous.items,
                unreadCount: previous.unreadCount,
            }));
        });
    }, [api]);

    const hasMore = state.items.length < state.total;

    return {
        items: state.items,
        unreadCount: state.unreadCount,
        loading: state.loading,
        loaded: state.loaded,
        hasMore,
        loadMore,
        markRead,
        markAllRead,
    };
}

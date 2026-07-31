import type {HttpClient} from '@alchemy/api';
import type {Notification, NotificationListResponse} from './types';

export type ListParams = {
    page?: number;
    limit?: number;
    unread?: boolean;
    signal?: AbortSignal;
};

/**
 * Thin wrapper around the notifier REST API. All calls go through the
 * authenticated {@link HttpClient} provided by the host application.
 */
export function createNotificationApi(client: HttpClient) {
    return {
        async list({
            page = 1,
            limit = 20,
            unread,
            signal,
        }: ListParams = {}): Promise<NotificationListResponse> {
            const res = await client.get<NotificationListResponse>(
                '/notifications',
                {
                    params: {
                        page,
                        limit,
                        ...(unread ? {unread: true} : {}),
                    },
                    signal,
                }
            );

            return res.data;
        },

        async unreadCount(signal?: AbortSignal): Promise<number> {
            const res = await client.get<{unreadCount: number}>(
                '/notifications/unread-count',
                {signal}
            );

            return res.data.unreadCount;
        },

        async markRead(id: string): Promise<Notification> {
            const res = await client.post<Notification>(
                `/notifications/${id}/read`
            );

            return res.data;
        },

        async markAllRead(): Promise<number> {
            const res = await client.post<{markedAsRead: number}>(
                '/notifications/read-all'
            );

            return res.data.markedAsRead;
        },
    };
}

export type NotificationApi = ReturnType<typeof createNotificationApi>;

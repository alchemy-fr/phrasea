import type {HttpClient} from '@alchemy/api';
import type {
    Notification,
    NotificationListResponse,
    NotificationPreference,
    NotificationPreferencesResponse,
} from './types';

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

        async markUnread(id: string): Promise<Notification> {
            const res = await client.post<Notification>(
                `/notifications/${id}/unread`
            );

            return res.data;
        },

        async remove(id: string): Promise<void> {
            await client.delete(`/notifications/${id}`);
        },

        async markAllRead(): Promise<number> {
            const res = await client.post<{markedAsRead: number}>(
                '/notifications/read-all'
            );

            return res.data.markedAsRead;
        },

        async listPreferences(
            signal?: AbortSignal
        ): Promise<NotificationPreference[]> {
            const res = await client.get<NotificationPreferencesResponse>(
                '/notification-preferences',
                {signal}
            );

            return res.data.items;
        },

        /**
         * Persists one or more preference changes and returns the full,
         * refreshed set of effective preferences.
         */
        async updatePreferences(
            items: NotificationPreference[]
        ): Promise<NotificationPreference[]> {
            const res = await client.put<NotificationPreferencesResponse>(
                '/notification-preferences',
                {items}
            );

            return res.data.items;
        },
    };
}

export type NotificationApi = ReturnType<typeof createNotificationApi>;

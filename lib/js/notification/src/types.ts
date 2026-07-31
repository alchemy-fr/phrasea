export type NotificationUriHandler = (uri: string) => void;

/**
 * A persisted in-app notification as returned by the notifier API.
 */
export type Notification = {
    id: string;
    topic: string;
    subject?: string | null;
    content?: string | null;
    data: NotificationData;
    read: boolean;
    readAt?: string | null;
    createdAt?: string | null;
};

export type NotificationData = {
    /**
     * Optional click-through target handled by the {@link NotificationUriHandler}.
     */
    uri?: string;
    [key: string]: unknown;
};

export type NotificationListResponse = {
    items: Notification[];
    total: number;
    page: number;
    limit: number;
    unreadCount: number;
};

/**
 * Real-time payload pushed on the subscriber channel when a new in-app
 * notification is emitted. Matches the InApp channel backend event.
 */
export type RealtimeNotification = {
    id: string;
    topic: string;
    subject?: string | null;
    content?: string | null;
    data?: NotificationData;
};

/**
 * Subscribes to the real-time notification channel (e.g. Pusher/Soketi).
 * Returns an unsubscribe function.
 *
 * The signature matches the app-level `registerWs(channel, event, callback)`
 * helper so it can be passed through directly.
 */
export type RegisterNotificationRealtime = (
    channel: string,
    event: string,
    callback: (data: RealtimeNotification) => void
) => () => void;

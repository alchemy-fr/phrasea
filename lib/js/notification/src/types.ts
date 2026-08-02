export type NotificationUriHandler = (uri: string) => void;

/**
 * A persisted in-app notification as returned by the notifier API.
 */
export type Notification = {
    id: string;
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
 * Delivery channel a notification can be routed through. Mirrors the backend
 * `ChannelType` enum.
 */
export type NotificationChannel = 'email' | 'sms' | 'in_app';

/**
 * A single (topic, channel) opt-in/opt-out as returned by the preferences API.
 * `enabled` reflects the *effective* value: `true` unless the subscriber has
 * explicitly opted out.
 */
export type NotificationPreference = {
    topic: string;
    channel: NotificationChannel;
    enabled: boolean;
};

export type NotificationPreferencesResponse = {
    items: NotificationPreference[];
};

/**
 * Real-time payload pushed on the subscriber channel when a new in-app
 * notification is emitted. Matches the InApp channel backend event: the
 * rendered subject/content and click-through uri are carried so the client can
 * display it immediately, without an extra API round-trip.
 */
export type RealtimeNotification = {
    id: string;
    subject?: string | null;
    content?: string | null;
    data?: NotificationData;
    createdAt?: string | null;
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

import Notifications from './src/components/Notifications';
import NotificationPreferences from './src/components/NotificationPreferences';

export {Notifications, NotificationPreferences};

export {useNotifications} from './src/useNotifications';
export type {UseNotificationsResult} from './src/useNotifications';
export {useNotificationPreferences} from './src/useNotificationPreferences';
export type {
    UseNotificationPreferencesResult,
    NotificationPreferenceTopic,
} from './src/useNotificationPreferences';
export {createNotificationApi} from './src/api';
export type {NotificationApi} from './src/api';
export {formatRelativeTime} from './src/time';

export * from './src/types';

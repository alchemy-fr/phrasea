import {NotificationUriRedirect} from '@/features/notifications/NotificationUriRedirect';

// The proxy (src/proxy.ts) redirects `/notification-uri` before any
// rendering: this page only shows when that redirection was skipped.
export default function NotificationUriPage() {
    return <NotificationUriRedirect />;
}

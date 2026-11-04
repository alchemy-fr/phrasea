import {FullPageLoader} from '@alchemy/next-ui/ui/loader';

// Resolution is handled client-side by NotificationUriListener (AppShell).
export default function NotificationUriPage() {
    return <FullPageLoader />;
}

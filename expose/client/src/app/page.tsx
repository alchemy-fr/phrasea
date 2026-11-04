import {notFound} from 'next/navigation';
import {getServerConfig} from '@/lib/config/server';
import {AdminShell} from '@/components/layout/AdminShell';
import {PublicationList} from '@/features/publications/PublicationList';

export default function IndexPage() {
    // The home page can be turned off: the publications are only reached
    // by their own URL
    if (getServerConfig().disableIndexPage) {
        notFound();
    }

    return (
        <AdminShell>
            <PublicationList />
        </AdminShell>
    );
}

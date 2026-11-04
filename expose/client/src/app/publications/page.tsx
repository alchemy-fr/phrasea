import {AdminShell} from '@/components/layout/AdminShell';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AdminPublicationList} from '@/features/admin/AdminPublicationList';

export default function AdminPublicationsPage() {
    return (
        <AdminShell>
            <RequireAuth>
                <AdminPublicationList />
            </RequireAuth>
        </AdminShell>
    );
}

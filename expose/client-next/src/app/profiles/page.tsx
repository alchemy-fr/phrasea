import {AdminShell} from '@/components/layout/AdminShell';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {ProfileList} from '@/features/admin/ProfileList';

export default function ProfilesPage() {
    return (
        <AdminShell>
            <RequireAuth>
                <ProfileList />
            </RequireAuth>
        </AdminShell>
    );
}

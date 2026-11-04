import {AdminShell} from '@/components/layout/AdminShell';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {ProfileEditScreen} from '@/features/admin/ProfileEditScreen';

export default function ProfileCreatePage() {
    return (
        <AdminShell>
            <RequireAuth>
                <ProfileEditScreen />
            </RequireAuth>
        </AdminShell>
    );
}

import {AdminShell} from '@/components/layout/AdminShell';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {ProfileEditScreen} from '@/features/admin/ProfileEditScreen';

export default async function ProfileEditPage({
    params,
}: {
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return (
        <AdminShell>
            <RequireAuth>
                <ProfileEditScreen id={decodeURIComponent(id)} />
            </RequireAuth>
        </AdminShell>
    );
}

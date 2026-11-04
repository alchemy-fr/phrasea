import {AdminShell} from '@/components/layout/AdminShell';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {PublicationEditScreen} from '@/features/admin/PublicationEditScreen';

export default async function PublicationEditPage({
    params,
}: {
    params: Promise<{id: string}>;
}) {
    const {id} = await params;

    return (
        <AdminShell>
            <RequireAuth>
                <PublicationEditScreen id={decodeURIComponent(id)} />
            </RequireAuth>
        </AdminShell>
    );
}

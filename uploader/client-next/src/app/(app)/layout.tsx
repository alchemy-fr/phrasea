import {PropsWithChildren} from 'react';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AppShell} from '@/components/layout/AppShell';

export default function AppLayout({children}: PropsWithChildren) {
    return (
        <AppShell>
            <RequireAuth>{children}</RequireAuth>
        </AppShell>
    );
}

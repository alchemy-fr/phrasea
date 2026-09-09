import type {ReactNode} from 'react';
import {AppShell} from '@/components/layout/AppShell';

export default function AppLayout({
    children,
    modal,
}: Readonly<{children: ReactNode; modal: ReactNode}>) {
    return (
        <AppShell>
            {children}
            {modal}
        </AppShell>
    );
}

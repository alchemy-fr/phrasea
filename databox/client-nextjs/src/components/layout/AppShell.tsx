'use client';

import {PropsWithChildren} from 'react';
import {useAuth} from '@/lib/auth/AuthProvider';
import {LeftPanel} from './LeftPanel';
import {TopBar} from './TopBar';
import {useLayoutStore} from './layoutStore';
import {cn} from '@/lib/utils/cn';
import {GlobalToasts} from '@/features/upload/GlobalToasts';
import {NotificationUriListener} from '@/features/notifications/NotificationUriListener';

export function AppShell({children}: PropsWithChildren) {
    const {status} = useAuth();
    const leftPanelOpen = useLayoutStore(s => s.leftPanelOpen);

    return (
        <div className="flex h-[100dvh] w-full flex-col overflow-hidden">
            <TopBar />
            <div className="flex min-h-0 flex-1">
                <aside
                    className={cn(
                        'flex shrink-0 flex-col border-r bg-sidebar text-sidebar-foreground transition-[width] duration-200',
                        leftPanelOpen
                            ? 'w-[300px]'
                            : 'w-0 overflow-hidden border-r-0'
                    )}
                >
                    {leftPanelOpen ? <LeftPanel /> : null}
                </aside>
                <main className="relative flex min-w-0 flex-1 flex-col overflow-hidden">
                    {children}
                </main>
            </div>
            {status === 'authenticated' ? (
                <>
                    <GlobalToasts />
                    <NotificationUriListener />
                </>
            ) : null}
        </div>
    );
}

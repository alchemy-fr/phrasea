'use client';

import {PropsWithChildren} from 'react';
import {useAuth} from '@/lib/auth/AuthProvider';
import {LeftPanel} from './LeftPanel';
import {TopBar} from './TopBar';
import {useLayoutStore} from './layoutStore';
import {cn} from '@/lib/utils/cn';
import {GlobalToasts} from '@/features/upload/GlobalToasts';
import {NotificationUriListener} from '@/features/notifications/NotificationUriListener';
import {TicketButton} from '@/features/ticketing/TicketButton';
import {AppDndProvider} from '@/features/dnd/AppDndProvider';
import {WorkspaceTermsGate} from '@/features/workspaces/terms/WorkspaceTermsGate';
import {ImpersonationBanner} from '@/features/impersonation/ImpersonationBanner';
import {useResizablePanel} from '@/hooks/useResizablePanel';
import {ResizeHandle} from '@/components/ui/resize-handle';

export function AppShell({children}: PropsWithChildren) {
    const {status} = useAuth();
    const leftPanelOpen = useLayoutStore(s => s.leftPanelOpen);
    const panel = useResizablePanel('left-panel');

    return (
        <AppDndProvider>
            <div className="flex h-[100dvh] w-full flex-col overflow-hidden">
                <TopBar />
                <ImpersonationBanner />
                <div className="flex min-h-0 flex-1">
                    <aside
                        data-testid="left-panel-aside"
                        style={leftPanelOpen ? {width: panel.width} : undefined}
                        className={cn(
                            'flex shrink-0 flex-col border-r bg-sidebar text-sidebar-foreground',
                            // Follows the pointer as it is resized, and
                            // takes the remembered width without animating
                            panel.loaded &&
                                !panel.resizing &&
                                'transition-[width] duration-200',
                            !leftPanelOpen && 'w-0 overflow-hidden border-r-0'
                        )}
                    >
                        {leftPanelOpen ? <LeftPanel /> : null}
                    </aside>
                    {leftPanelOpen ? (
                        <ResizeHandle
                            panel={panel}
                            data-testid="left-panel-resize"
                        />
                    ) : null}
                    <main className="relative flex min-w-0 flex-1 flex-col overflow-hidden">
                        {children}
                    </main>
                </div>
                {status === 'authenticated' ? (
                    <>
                        <GlobalToasts />
                        <NotificationUriListener />
                        <TicketButton />
                        <WorkspaceTermsGate />
                    </>
                ) : null}
            </div>
        </AppDndProvider>
    );
}

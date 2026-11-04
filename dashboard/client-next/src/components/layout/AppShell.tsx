import {PropsWithChildren} from 'react';
import {TopBar} from './TopBar';

export function AppShell({children}: PropsWithChildren) {
    return (
        <div className="flex min-h-[100dvh] w-full flex-col">
            <TopBar />
            <main className="min-h-0 flex-1">{children}</main>
        </div>
    );
}

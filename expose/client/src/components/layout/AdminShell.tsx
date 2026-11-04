'use client';

import {PropsWithChildren} from 'react';
import {TopBar} from './TopBar';
import {AdminNav} from './AdminNav';

/** Shell of the list of publications and of the administration screens */
export function AdminShell({children}: PropsWithChildren) {
    return (
        <div className="flex min-h-[100dvh] flex-col">
            <TopBar>
                <span className="ml-2 hidden h-5 w-px bg-border sm:block" />
                <AdminNav />
            </TopBar>
            <main className="flex flex-1 flex-col">{children}</main>
        </div>
    );
}

'use client';

import {PropsWithChildren} from 'react';
import {TopBar} from './TopBar';

export function AppShell({children}: PropsWithChildren) {
    return (
        <div className="flex h-[100dvh] w-full flex-col overflow-hidden">
            <TopBar />
            <main className="relative min-h-0 flex-1 overflow-auto">
                {children}
            </main>
        </div>
    );
}

/** Centered column of a page, with its title */
export function Page({
    title,
    actions,
    children,
    wide,
}: PropsWithChildren<{
    title?: React.ReactNode;
    actions?: React.ReactNode;
    wide?: boolean;
}>) {
    return (
        <div
            className={
                wide
                    ? 'mx-auto w-full max-w-7xl px-4 py-6'
                    : 'mx-auto w-full max-w-4xl px-4 py-6'
            }
        >
            {title || actions ? (
                <div className="mb-6 flex flex-wrap items-center gap-3">
                    {title ? (
                        <h1
                            data-testid="page-title"
                            className="min-w-0 flex-1 truncate text-2xl font-semibold"
                        >
                            {title}
                        </h1>
                    ) : null}
                    {actions ? (
                        <div className="flex items-center gap-2">{actions}</div>
                    ) : null}
                </div>
            ) : null}
            {children}
        </div>
    );
}

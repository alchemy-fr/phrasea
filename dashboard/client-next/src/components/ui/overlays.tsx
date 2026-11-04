'use client';

import * as React from 'react';
import {Tooltip as TooltipPrimitive} from 'radix-ui';

// ---------------------------------------------------------------------------
// Tooltip

export function Tooltip({
    children,
    content,
    side,
    align,
    disabled,
    delayDuration,
}: {
    children: React.ReactNode;
    content: React.ReactNode;
    side?: 'top' | 'right' | 'bottom' | 'left';
    align?: 'start' | 'center' | 'end';
    disabled?: boolean;
    delayDuration?: number;
}) {
    if (disabled || !content) {
        return <>{children}</>;
    }

    return (
        <TooltipPrimitive.Root delayDuration={delayDuration}>
            <TooltipPrimitive.Trigger asChild>
                {children}
            </TooltipPrimitive.Trigger>
            <TooltipPrimitive.Portal>
                <TooltipPrimitive.Content
                    side={side}
                    align={align}
                    sideOffset={4}
                    className="z-[60] max-w-xs rounded-md bg-foreground px-2.5 py-1.5 text-xs text-background shadow-md animate-in fade-in-0 zoom-in-95"
                >
                    {content}
                    <TooltipPrimitive.Arrow className="fill-foreground" />
                </TooltipPrimitive.Content>
            </TooltipPrimitive.Portal>
        </TooltipPrimitive.Root>
    );
}

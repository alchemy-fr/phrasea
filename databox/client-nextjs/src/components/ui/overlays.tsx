'use client';

import * as React from 'react';
import {
    Popover as PopoverPrimitive,
    Tooltip as TooltipPrimitive,
    Dialog as SheetPrimitive,
} from 'radix-ui';
import {XIcon} from 'lucide-react';
import {cn} from '@/lib/utils/cn';

// ---------------------------------------------------------------------------
// Popover

export const Popover = PopoverPrimitive.Root;
export const PopoverTrigger = PopoverPrimitive.Trigger;
export const PopoverAnchor = PopoverPrimitive.Anchor;
export const PopoverClose = PopoverPrimitive.Close;

export function PopoverContent({
    className,
    align = 'center',
    sideOffset = 4,
    ...props
}: React.ComponentProps<typeof PopoverPrimitive.Content>) {
    return (
        <PopoverPrimitive.Portal>
            <PopoverPrimitive.Content
                align={align}
                sideOffset={sideOffset}
                className={cn(
                    'z-50 w-72 rounded-md border bg-popover p-4 text-popover-foreground shadow-md outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                    className
                )}
                {...props}
            />
        </PopoverPrimitive.Portal>
    );
}

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

// ---------------------------------------------------------------------------
// Sheet (side panel)

export const Sheet = SheetPrimitive.Root;
export const SheetTrigger = SheetPrimitive.Trigger;
export const SheetClose = SheetPrimitive.Close;

export function SheetContent({
    className,
    children,
    side = 'right',
    ...props
}: React.ComponentProps<typeof SheetPrimitive.Content> & {
    side?: 'top' | 'right' | 'bottom' | 'left';
}) {
    return (
        <SheetPrimitive.Portal>
            <SheetPrimitive.Overlay className="fixed inset-0 z-50 bg-black/50 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
            <SheetPrimitive.Content
                className={cn(
                    'fixed z-50 flex flex-col gap-4 bg-background shadow-lg transition ease-in-out data-[state=closed]:duration-300 data-[state=open]:duration-300 data-[state=open]:animate-in data-[state=closed]:animate-out',
                    side === 'right' &&
                        'inset-y-0 right-0 h-full w-3/4 border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right sm:max-w-sm',
                    side === 'left' &&
                        'inset-y-0 left-0 h-full w-3/4 border-r data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left sm:max-w-sm',
                    side === 'top' &&
                        'inset-x-0 top-0 h-auto border-b data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top',
                    side === 'bottom' &&
                        'inset-x-0 bottom-0 h-auto border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom',
                    className
                )}
                {...props}
            >
                {children}
                <SheetPrimitive.Close className="absolute top-4 right-4 rounded-sm opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-ring focus:outline-none">
                    <XIcon className="size-4" />
                    <span className="sr-only">Close</span>
                </SheetPrimitive.Close>
            </SheetPrimitive.Content>
        </SheetPrimitive.Portal>
    );
}

export function SheetHeader({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            className={cn('flex flex-col gap-1.5 p-4', className)}
            {...props}
        />
    );
}

export function SheetTitle({
    className,
    ...props
}: React.ComponentProps<typeof SheetPrimitive.Title>) {
    return (
        <SheetPrimitive.Title
            className={cn('text-base font-semibold', className)}
            {...props}
        />
    );
}

'use client';

import * as React from 'react';
import {DropdownMenu as DM, ContextMenu as CM} from 'radix-ui';
import {CheckIcon, ChevronRightIcon, CircleIcon} from 'lucide-react';
import {cn} from '@/lib/utils/cn';

const contentClass =
    'z-50 min-w-[10rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95';
const itemClass =
    "relative flex cursor-default select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 data-[variant=destructive]:text-destructive data-[variant=destructive]:data-[highlighted]:bg-destructive/10";
const labelClass = 'px-2 py-1.5 text-xs font-medium text-muted-foreground';
const separatorClass = '-mx-1 my-1 h-px bg-border';
const shortcutClass = 'ml-auto text-xs tracking-widest text-muted-foreground';

// ---------------------------------------------------------------------------
// Dropdown menu

export const DropdownMenu = DM.Root;
export const DropdownMenuTrigger = DM.Trigger;
export const DropdownMenuGroup = DM.Group;
export const DropdownMenuSub = DM.Sub;
export const DropdownMenuRadioGroup = DM.RadioGroup;

export function DropdownMenuContent({
    className,
    sideOffset = 4,
    ...props
}: React.ComponentProps<typeof DM.Content>) {
    return (
        <DM.Portal>
            <DM.Content
                sideOffset={sideOffset}
                className={cn(contentClass, className)}
                {...props}
            />
        </DM.Portal>
    );
}

export function DropdownMenuItem({
    className,
    inset,
    variant = 'default',
    ...props
}: React.ComponentProps<typeof DM.Item> & {
    inset?: boolean;
    variant?: 'default' | 'destructive';
}) {
    return (
        <DM.Item
            data-variant={variant}
            className={cn(itemClass, inset && 'pl-8', className)}
            {...props}
        />
    );
}

export function DropdownMenuCheckboxItem({
    className,
    children,
    checked,
    ...props
}: React.ComponentProps<typeof DM.CheckboxItem>) {
    return (
        <DM.CheckboxItem
            className={cn(itemClass, 'pl-8', className)}
            checked={checked}
            {...props}
        >
            <span className="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
                <DM.ItemIndicator>
                    <CheckIcon className="size-4" />
                </DM.ItemIndicator>
            </span>
            {children}
        </DM.CheckboxItem>
    );
}

export function DropdownMenuRadioItem({
    className,
    children,
    ...props
}: React.ComponentProps<typeof DM.RadioItem>) {
    return (
        <DM.RadioItem className={cn(itemClass, 'pl-8', className)} {...props}>
            <span className="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
                <DM.ItemIndicator>
                    <CircleIcon className="size-2 fill-current" />
                </DM.ItemIndicator>
            </span>
            {children}
        </DM.RadioItem>
    );
}

export function DropdownMenuLabel({
    className,
    ...props
}: React.ComponentProps<typeof DM.Label>) {
    return <DM.Label className={cn(labelClass, className)} {...props} />;
}

export function DropdownMenuSeparator({
    className,
    ...props
}: React.ComponentProps<typeof DM.Separator>) {
    return (
        <DM.Separator className={cn(separatorClass, className)} {...props} />
    );
}

export function DropdownMenuShortcut({
    className,
    ...props
}: React.ComponentProps<'span'>) {
    return <span className={cn(shortcutClass, className)} {...props} />;
}

export function DropdownMenuSubTrigger({
    className,
    children,
    ...props
}: React.ComponentProps<typeof DM.SubTrigger>) {
    return (
        <DM.SubTrigger className={cn(itemClass, className)} {...props}>
            {children}
            <ChevronRightIcon className="ml-auto size-4" />
        </DM.SubTrigger>
    );
}

export function DropdownMenuSubContent({
    className,
    ...props
}: React.ComponentProps<typeof DM.SubContent>) {
    return (
        <DM.Portal>
            <DM.SubContent className={cn(contentClass, className)} {...props} />
        </DM.Portal>
    );
}

// ---------------------------------------------------------------------------
// Context menu

export const ContextMenu = CM.Root;
export const ContextMenuTrigger = CM.Trigger;
export const ContextMenuGroup = CM.Group;
export const ContextMenuSub = CM.Sub;

export function ContextMenuContent({
    className,
    ...props
}: React.ComponentProps<typeof CM.Content>) {
    return (
        <CM.Portal>
            <CM.Content className={cn(contentClass, className)} {...props} />
        </CM.Portal>
    );
}

export function ContextMenuItem({
    className,
    inset,
    variant = 'default',
    ...props
}: React.ComponentProps<typeof CM.Item> & {
    inset?: boolean;
    variant?: 'default' | 'destructive';
}) {
    return (
        <CM.Item
            data-variant={variant}
            className={cn(itemClass, inset && 'pl-8', className)}
            {...props}
        />
    );
}

export function ContextMenuCheckboxItem({
    className,
    children,
    ...props
}: React.ComponentProps<typeof CM.CheckboxItem>) {
    return (
        <CM.CheckboxItem
            className={cn(itemClass, 'pl-8', className)}
            {...props}
        >
            <span className="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
                <CM.ItemIndicator>
                    <CheckIcon className="size-4" />
                </CM.ItemIndicator>
            </span>
            {children}
        </CM.CheckboxItem>
    );
}

export function ContextMenuLabel({
    className,
    ...props
}: React.ComponentProps<typeof CM.Label>) {
    return <CM.Label className={cn(labelClass, className)} {...props} />;
}

export function ContextMenuSeparator({
    className,
    ...props
}: React.ComponentProps<typeof CM.Separator>) {
    return (
        <CM.Separator className={cn(separatorClass, className)} {...props} />
    );
}

export function ContextMenuSubTrigger({
    className,
    children,
    ...props
}: React.ComponentProps<typeof CM.SubTrigger>) {
    return (
        <CM.SubTrigger className={cn(itemClass, className)} {...props}>
            {children}
            <ChevronRightIcon className="ml-auto size-4" />
        </CM.SubTrigger>
    );
}

export function ContextMenuSubContent({
    className,
    ...props
}: React.ComponentProps<typeof CM.SubContent>) {
    return (
        <CM.Portal>
            <CM.SubContent className={cn(contentClass, className)} {...props} />
        </CM.Portal>
    );
}

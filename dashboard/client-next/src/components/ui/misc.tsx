'use client';

import * as React from 'react';
import {Avatar as AvatarPrimitive} from 'radix-ui';
import {cva, type VariantProps} from 'class-variance-authority';
import {cn} from '@/lib/utils/cn';

// ---------------------------------------------------------------------------
// Badge

export const badgeVariants = cva(
    'inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-colors [&>svg]:pointer-events-none [&>svg]:size-3',
    {
        variants: {
            variant: {
                default:
                    'border-transparent bg-primary text-primary-foreground',
                secondary:
                    'border-transparent bg-secondary text-secondary-foreground',
                destructive:
                    'border-transparent bg-destructive text-destructive-foreground',
                warning:
                    'border-transparent bg-warning text-warning-foreground',
                success:
                    'border-transparent bg-success text-success-foreground',
                outline: 'text-foreground',
                muted: 'border-transparent bg-muted text-muted-foreground',
            },
        },
        defaultVariants: {variant: 'default'},
    }
);

export function Badge({
    className,
    variant,
    ...props
}: React.ComponentProps<'span'> & VariantProps<typeof badgeVariants>) {
    return (
        <span className={cn(badgeVariants({variant}), className)} {...props} />
    );
}

// ---------------------------------------------------------------------------
// Avatar, Alert

export function Avatar({
    className,
    name,
    src,
    size = 'md',
}: {
    className?: string;
    name: string;
    src?: string;
    size?: 'sm' | 'md' | 'lg';
}) {
    const initials = name
        .split(/[\s._-]+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(p => p[0]?.toUpperCase())
        .join('');

    return (
        <AvatarPrimitive.Root
            className={cn(
                'relative flex shrink-0 overflow-hidden rounded-full bg-accent text-accent-foreground',
                size === 'sm' && 'size-6 text-[10px]',
                size === 'md' && 'size-8 text-xs',
                size === 'lg' && 'size-10 text-sm',
                className
            )}
        >
            {src ? (
                <AvatarPrimitive.Image
                    src={src}
                    className="aspect-square size-full"
                />
            ) : null}
            <AvatarPrimitive.Fallback className="flex size-full items-center justify-center font-medium">
                {initials || '?'}
            </AvatarPrimitive.Fallback>
        </AvatarPrimitive.Root>
    );
}

export function Alert({
    className,
    variant = 'default',
    title,
    children,
    icon,
    ...props
}: React.ComponentProps<'div'> & {
    variant?: 'default' | 'info' | 'warning' | 'destructive' | 'success';
    title?: React.ReactNode;
    icon?: React.ReactNode;
}) {
    return (
        <div
            role="alert"
            className={cn(
                'relative flex w-full gap-3 rounded-lg border px-4 py-3 text-sm [&>svg]:mt-0.5 [&>svg]:size-4 [&>svg]:shrink-0',
                variant === 'default' && 'bg-card text-card-foreground',
                variant === 'info' &&
                    'border-primary/30 bg-primary/5 text-foreground [&>svg]:text-primary',
                variant === 'warning' &&
                    'border-warning/50 bg-warning/10 text-foreground [&>svg]:text-warning-foreground',
                variant === 'destructive' &&
                    'border-destructive/40 bg-destructive/5 text-destructive',
                variant === 'success' &&
                    'border-success/40 bg-success/10 text-foreground [&>svg]:text-success',
                className
            )}
            {...props}
        >
            {icon}
            <div className="min-w-0 flex-1">
                {title ? (
                    <div className="mb-0.5 font-medium">{title}</div>
                ) : null}
                <div className="[&_p]:leading-relaxed">{children}</div>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Empty state

export function EmptyState({
    icon,
    title,
    description,
    action,
    className,
    testId,
}: {
    icon?: React.ReactNode;
    title: React.ReactNode;
    description?: React.ReactNode;
    action?: React.ReactNode;
    className?: string;
    testId?: string;
}) {
    return (
        <div
            data-testid={testId}
            className={cn(
                'flex flex-col items-center justify-center gap-2 p-8 text-center text-muted-foreground',
                className
            )}
        >
            {icon ? (
                <div className="mb-2 [&>svg]:size-10 [&>svg]:opacity-40">
                    {icon}
                </div>
            ) : null}
            <div className="text-base font-medium text-foreground">{title}</div>
            {description ? (
                <div className="max-w-md text-sm">{description}</div>
            ) : null}
            {action ? <div className="mt-3">{action}</div> : null}
        </div>
    );
}

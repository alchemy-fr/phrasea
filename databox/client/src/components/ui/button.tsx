'use client';

import * as React from 'react';
import {Slot} from 'radix-ui';
import {cva, type VariantProps} from 'class-variance-authority';
import {Loader2} from 'lucide-react';
import {cn} from '@/lib/utils/cn';

export const buttonVariants = cva(
    "inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring/60 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default:
                    'bg-primary text-primary-foreground hover:bg-primary/90',
                destructive:
                    'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                outline:
                    'border bg-background hover:bg-accent hover:text-accent-foreground',
                secondary:
                    'bg-secondary text-secondary-foreground hover:bg-secondary/80',
                ghost: 'hover:bg-accent hover:text-accent-foreground',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                'default': 'h-9 px-4 py-2',
                'sm': 'h-8 rounded-md px-3 text-xs',
                'lg': 'h-10 rounded-md px-6',
                'icon': 'size-9',
                'icon-sm': 'size-8',
                'icon-xs': 'size-7 [&_svg:not([class*=size-])]:size-3.5',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);

export type ButtonProps = React.ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
        /**
         * Shows a spinner in place of the leading icon and disables the
         * button. Automatic while the promise returned by `onClick` (a
         * request) is pending.
         */
        loading?: boolean;
    };

export function Button({
    className,
    variant,
    size,
    asChild = false,
    loading = false,
    disabled,
    children,
    // Buttons sit inside <form> elements (see FormDialog): never let one
    // submit by accident just because it was not given a type.
    type = 'button',
    onClick,
    ...props
}: ButtonProps) {
    const [pending, setPending] = React.useState(false);
    const busy = loading || pending;
    const classes = cn(
        buttonVariants({variant, size, className}),
        // The spinner takes the place of the leading icon
        busy && '[&>[data-slot=spinner]+svg]:hidden'
    );

    if (asChild) {
        // Slot requires a single element child: no loader injection here.
        return (
            <Slot.Root
                data-slot="button"
                className={classes}
                onClick={onClick}
                {...props}
            >
                {children}
            </Slot.Root>
        );
    }

    return (
        <button
            data-slot="button"
            type={type}
            className={classes}
            disabled={disabled || busy}
            onClick={
                onClick
                    ? e => {
                          const result: unknown = onClick(e);
                          if (isRequest(result)) {
                              setPending(true);
                              // Rethrown: still reported as before
                              result.then(
                                  () => setPending(false),
                                  (error: unknown) => {
                                      setPending(false);
                                      throw error;
                                  }
                              );
                          }
                      }
                    : undefined
            }
            {...props}
        >
            {busy ? (
                <Loader2 data-slot="spinner" className="animate-spin" />
            ) : null}
            {children}
        </button>
    );
}

/**
 * A promise returned by a click handler is taken for a request, except the
 * handle of a modal (`openModal()`), which only settles once it is closed.
 */
function isRequest(result: unknown): result is Promise<unknown> {
    return (
        result instanceof Promise &&
        typeof (result as {close?: unknown}).close !== 'function'
    );
}

import * as React from 'react';
import {cn} from '@/lib/utils/cn';

export function Input({
    className,
    type,
    ...props
}: React.ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors outline-none file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/60 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/30',
                className
            )}
            {...props}
        />
    );
}

export function Textarea({
    className,
    ...props
}: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/60 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive',
                className
            )}
            {...props}
        />
    );
}

export function Label({className, ...props}: React.ComponentProps<'label'>) {
    return (
        <label
            data-slot="label"
            className={cn(
                'flex items-center gap-2 text-sm font-medium leading-none select-none peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
                className
            )}
            {...props}
        />
    );
}

export function FieldError({children}: {children?: React.ReactNode}) {
    if (!children) {
        return null;
    }

    return <p className="mt-1 text-xs text-destructive">{children}</p>;
}

export function FieldHelp({children}: {children?: React.ReactNode}) {
    if (!children) {
        return null;
    }

    return <p className="mt-1 text-xs text-muted-foreground">{children}</p>;
}

export function FormRow({
    label,
    htmlFor,
    error,
    help,
    children,
    className,
    inline,
}: {
    label?: React.ReactNode;
    htmlFor?: string;
    error?: React.ReactNode;
    help?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
    inline?: boolean;
}) {
    return (
        <div
            className={cn(
                'mb-4',
                inline && 'flex items-center gap-3',
                className
            )}
        >
            {label ? (
                <Label htmlFor={htmlFor} className={cn(!inline && 'mb-1.5')}>
                    {label}
                </Label>
            ) : null}
            {children}
            <FieldError>{error}</FieldError>
            <FieldHelp>{help}</FieldHelp>
        </div>
    );
}

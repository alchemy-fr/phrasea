'use client';

import * as React from 'react';
import {
    Tabs as TabsPrimitive,
    ScrollArea as ScrollAreaPrimitive,
    Separator as SeparatorPrimitive,
    Accordion as AccordionPrimitive,
    Progress as ProgressPrimitive,
    Avatar as AvatarPrimitive,
} from 'radix-ui';
import {cva, type VariantProps} from 'class-variance-authority';
import {ChevronDownIcon} from 'lucide-react';
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
// Chip (removable / clickable tag-like element)

export function Chip({
    className,
    color,
    size = 'sm',
    onRemove,
    icon,
    children,
    ...props
}: React.ComponentProps<'span'> & {
    color?: string | null;
    size?: 'xs' | 'sm' | 'md';
    onRemove?: () => void;
    icon?: React.ReactNode;
}) {
    const style = color
        ? {
              backgroundColor: color,
              color: contrastColor(color),
              borderColor: color,
          }
        : undefined;

    return (
        <span
            className={cn(
                'inline-flex max-w-full items-center gap-1 rounded-full border bg-secondary text-secondary-foreground',
                size === 'xs' && 'h-5 px-1.5 text-[11px]',
                size === 'sm' && 'h-6 px-2 text-xs',
                size === 'md' && 'h-7 px-2.5 text-sm',
                props.onClick && 'cursor-pointer hover:opacity-80',
                className
            )}
            style={style}
            {...props}
        >
            {icon}
            <span className="truncate">{children}</span>
            {onRemove ? (
                <button
                    type="button"
                    className="-mr-1 rounded-full p-0.5 opacity-70 hover:opacity-100"
                    onClick={e => {
                        e.stopPropagation();
                        onRemove();
                    }}
                    aria-label="Remove"
                >
                    <svg
                        viewBox="0 0 12 12"
                        className="size-3"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.6}
                    >
                        <path d="M3 3l6 6M9 3l-6 6" />
                    </svg>
                </button>
            ) : null}
        </span>
    );
}

export function contrastColor(hex: string): string {
    const m = hex.replace('#', '');
    if (m.length !== 6 && m.length !== 3) {
        return 'inherit';
    }
    const full =
        m.length === 3
            ? m
                  .split('')
                  .map(c => c + c)
                  .join('')
            : m;
    const r = parseInt(full.slice(0, 2), 16);
    const g = parseInt(full.slice(2, 4), 16);
    const b = parseInt(full.slice(4, 6), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

    return luminance > 0.6 ? '#111' : '#fff';
}

// ---------------------------------------------------------------------------
// Tabs

export const Tabs = TabsPrimitive.Root;

export function TabsList({
    className,
    ...props
}: React.ComponentProps<typeof TabsPrimitive.List>) {
    return (
        <TabsPrimitive.List
            className={cn(
                'inline-flex h-9 w-fit items-center justify-center gap-1 rounded-lg bg-muted p-1 text-muted-foreground',
                className
            )}
            {...props}
        />
    );
}

export function TabsTrigger({
    className,
    ...props
}: React.ComponentProps<typeof TabsPrimitive.Trigger>) {
    return (
        <TabsPrimitive.Trigger
            className={cn(
                "inline-flex h-full flex-1 items-center justify-center gap-1.5 rounded-md border border-transparent px-3 py-1 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
                className
            )}
            {...props}
        />
    );
}

export function TabsContent({
    className,
    ...props
}: React.ComponentProps<typeof TabsPrimitive.Content>) {
    return (
        <TabsPrimitive.Content
            className={cn('flex-1 outline-none', className)}
            {...props}
        />
    );
}

/** Underlined tabs used in dialogs */
export function UnderlineTabsList({
    className,
    ...props
}: React.ComponentProps<typeof TabsPrimitive.List>) {
    return (
        <TabsPrimitive.List
            className={cn(
                'flex w-full items-center gap-1 overflow-x-auto border-b no-scrollbar',
                className
            )}
            {...props}
        />
    );
}

export function UnderlineTabsTrigger({
    className,
    ...props
}: React.ComponentProps<typeof TabsPrimitive.Trigger>) {
    return (
        <TabsPrimitive.Trigger
            className={cn(
                '-mb-px inline-flex h-10 shrink-0 items-center gap-1.5 border-b-2 border-transparent px-3 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 data-[state=active]:border-primary data-[state=active]:text-foreground [&_svg]:size-4',
                className
            )}
            {...props}
        />
    );
}

// ---------------------------------------------------------------------------
// ScrollArea

export function ScrollArea({
    className,
    children,
    viewportRef,
    viewportClassName,
    onScroll,
    ...props
}: React.ComponentProps<typeof ScrollAreaPrimitive.Root> & {
    viewportRef?: React.Ref<HTMLDivElement>;
    viewportClassName?: string;
    onScroll?: React.UIEventHandler<HTMLDivElement>;
}) {
    return (
        <ScrollAreaPrimitive.Root
            className={cn('relative overflow-hidden', className)}
            {...props}
        >
            <ScrollAreaPrimitive.Viewport
                ref={viewportRef}
                onScroll={onScroll}
                className={cn(
                    'size-full rounded-[inherit] [&>div]:!block',
                    viewportClassName
                )}
            >
                {children}
            </ScrollAreaPrimitive.Viewport>
            <ScrollBar />
            <ScrollBar orientation="horizontal" />
            <ScrollAreaPrimitive.Corner />
        </ScrollAreaPrimitive.Root>
    );
}

function ScrollBar({
    className,
    orientation = 'vertical',
    ...props
}: React.ComponentProps<typeof ScrollAreaPrimitive.ScrollAreaScrollbar>) {
    return (
        <ScrollAreaPrimitive.ScrollAreaScrollbar
            orientation={orientation}
            className={cn(
                'flex touch-none p-px transition-colors select-none',
                orientation === 'vertical' &&
                    'h-full w-2.5 border-l border-l-transparent',
                orientation === 'horizontal' &&
                    'h-2.5 flex-col border-t border-t-transparent',
                className
            )}
            {...props}
        >
            <ScrollAreaPrimitive.ScrollAreaThumb className="relative flex-1 rounded-full bg-foreground/25" />
        </ScrollAreaPrimitive.ScrollAreaScrollbar>
    );
}

// ---------------------------------------------------------------------------
// Separator, Skeleton, Progress, Avatar, Alert, Kbd

export function Separator({
    className,
    orientation = 'horizontal',
    decorative = true,
    ...props
}: React.ComponentProps<typeof SeparatorPrimitive.Root>) {
    return (
        <SeparatorPrimitive.Root
            decorative={decorative}
            orientation={orientation}
            className={cn(
                'shrink-0 bg-border data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-px',
                className
            )}
            {...props}
        />
    );
}

export function Skeleton({className, ...props}: React.ComponentProps<'div'>) {
    return (
        <div
            className={cn('animate-pulse rounded-md bg-muted', className)}
            {...props}
        />
    );
}

export function Progress({
    className,
    value,
    indeterminate,
    ...props
}: React.ComponentProps<typeof ProgressPrimitive.Root> & {
    indeterminate?: boolean;
}) {
    return (
        <ProgressPrimitive.Root
            className={cn(
                'relative h-1.5 w-full overflow-hidden rounded-full bg-muted',
                className
            )}
            value={value}
            {...props}
        >
            <ProgressPrimitive.Indicator
                className={cn(
                    'h-full bg-primary transition-transform',
                    indeterminate &&
                        'w-1/3 animate-[indeterminate_1.2s_ease-in-out_infinite]'
                )}
                style={
                    indeterminate
                        ? undefined
                        : {transform: `translateX(-${100 - (value ?? 0)}%)`}
                }
            />
        </ProgressPrimitive.Root>
    );
}

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

export function Kbd({className, ...props}: React.ComponentProps<'kbd'>) {
    return (
        <kbd
            className={cn(
                'inline-flex h-5 min-w-5 items-center justify-center rounded border bg-muted px-1 font-mono text-[10px] font-medium text-muted-foreground',
                className
            )}
            {...props}
        />
    );
}

// ---------------------------------------------------------------------------
// Accordion

export const Accordion = AccordionPrimitive.Root;

export function AccordionItem({
    className,
    ...props
}: React.ComponentProps<typeof AccordionPrimitive.Item>) {
    return (
        <AccordionPrimitive.Item
            className={cn('border-b last:border-b-0', className)}
            {...props}
        />
    );
}

export function AccordionTrigger({
    className,
    children,
    actions,
    ...props
}: React.ComponentProps<typeof AccordionPrimitive.Trigger> & {
    actions?: React.ReactNode;
}) {
    return (
        <AccordionPrimitive.Header className="flex items-center">
            <AccordionPrimitive.Trigger
                className={cn(
                    'flex flex-1 items-center justify-between gap-4 py-3 text-left text-sm font-medium transition-all outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring/60 disabled:pointer-events-none disabled:opacity-50 [&[data-state=open]>svg]:rotate-180',
                    className
                )}
                {...props}
            >
                {children}
                <ChevronDownIcon className="size-4 shrink-0 text-muted-foreground transition-transform duration-200" />
            </AccordionPrimitive.Trigger>
            {actions}
        </AccordionPrimitive.Header>
    );
}

export function AccordionContent({
    className,
    children,
    ...props
}: React.ComponentProps<typeof AccordionPrimitive.Content>) {
    return (
        <AccordionPrimitive.Content
            className="overflow-hidden text-sm data-[state=closed]:animate-accordion-up data-[state=open]:animate-accordion-down"
            {...props}
        >
            <div className={cn('pt-0 pb-3', className)}>{children}</div>
        </AccordionPrimitive.Content>
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
}: {
    icon?: React.ReactNode;
    title: React.ReactNode;
    description?: React.ReactNode;
    action?: React.ReactNode;
    className?: string;
}) {
    return (
        <div
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

'use client';

import {ReactNode, useState} from 'react';
import {ChevronDownIcon} from 'lucide-react';
import {cn} from '@/lib/utils/cn';

export function PanelSection({
    title,
    icon,
    actions,
    children,
    defaultOpen = true,
    className,
}: {
    title: ReactNode;
    icon?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    defaultOpen?: boolean;
    className?: string;
}) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <section className={cn('border-b', className)}>
            <div className="flex items-center gap-1 px-2 py-1.5">
                <button
                    type="button"
                    className="flex min-w-0 flex-1 items-center gap-2 rounded px-1 py-1 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase hover:bg-accent [&>svg]:size-4"
                    onClick={() => setOpen(o => !o)}
                >
                    {icon}
                    <span className="truncate">{title}</span>
                    <ChevronDownIcon
                        className={cn(
                            'ml-auto transition-transform',
                            !open && '-rotate-90'
                        )}
                    />
                </button>
                {actions}
            </div>
            {open ? children : null}
        </section>
    );
}

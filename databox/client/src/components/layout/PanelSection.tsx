'use client';

import {ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {ChevronDownIcon, GripVerticalIcon} from 'lucide-react';
import {cn} from '@/lib/utils/cn';
import {useSectionGrip} from './SortableSections';

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
    const {t} = useTranslation();
    const [open, setOpen] = useState(defaultOpen);
    // Inside `SortableSections`: the header carries the grip moving it
    const grip = useSectionGrip();

    return (
        <section className={cn('group/section border-b', className)}>
            <div className="flex items-center gap-1 px-2 py-1.5">
                {grip ? (
                    <span
                        role="button"
                        tabIndex={0}
                        data-section-grip
                        data-testid="section-grip"
                        aria-label={t(
                            'panel.section.move',
                            'Move the section (arrow keys)'
                        )}
                        title={t('panel.section.move_help', 'Drag to reorder')}
                        onPointerDown={grip.onPointerDown}
                        onKeyDown={grip.onKeyDown}
                        className={cn(
                            '-mx-1 flex h-6 w-4 shrink-0 cursor-grab touch-none items-center justify-center rounded text-muted-foreground opacity-0 group-hover/section:opacity-100 hover:bg-accent focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing [&>svg]:size-3.5',
                            grip.dragging && 'opacity-100'
                        )}
                    >
                        <GripVerticalIcon />
                    </span>
                ) : null}
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

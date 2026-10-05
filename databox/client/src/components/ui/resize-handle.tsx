'use client';

import {useTranslation} from 'react-i18next';
import type {useResizablePanel} from '@/hooks/useResizablePanel';
import {cn} from '@/lib/utils/cn';

/**
 * The grip resizing a panel (see `useResizablePanel`), laid between the panel
 * and its neighbour. It takes no room: a strip straddling the border, wider
 * than it looks to be easy to grab.
 */
export function ResizeHandle({
    panel,
    className,
    ...props
}: {
    'panel': Pick<
        ReturnType<typeof useResizablePanel>,
        'resizing' | 'onPointerDown' | 'onKeyDown'
    >;
    'className'?: string;
    'data-testid'?: string;
}) {
    const {t} = useTranslation();

    return (
        <div className="relative w-0 shrink-0">
            <div
                {...props}
                data-resize-handle
                role="separator"
                aria-orientation="vertical"
                aria-label={t('panel.resize', 'Resize the panel')}
                tabIndex={0}
                onPointerDown={panel.onPointerDown}
                onKeyDown={panel.onKeyDown}
                className={cn(
                    'absolute inset-y-0 -left-[3px] z-20 w-1.5 cursor-col-resize touch-none transition-colors hover:bg-primary/60 focus-visible:bg-primary focus-visible:outline-none',
                    panel.resizing && 'bg-primary',
                    className
                )}
            />
        </div>
    );
}

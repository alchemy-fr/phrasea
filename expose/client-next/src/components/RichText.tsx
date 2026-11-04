'use client';

import {useMemo} from 'react';
import {installLinkHook, sanitizeHtml} from '@/lib/utils/html';
import {cn} from '@/lib/utils/cn';

installLinkHook();

/** HTML written by the editors, sanitized */
export function RichText({
    html,
    className,
    ...props
}: {
    html: string | null | undefined;
    className?: string;
} & Omit<React.ComponentProps<'div'>, 'children' | 'dangerouslySetInnerHTML'>) {
    const clean = useMemo(() => sanitizeHtml(html), [html]);
    if (!clean) {
        return null;
    }

    return (
        <div
            {...props}
            className={cn('rich-text', className)}
            dangerouslySetInnerHTML={{__html: clean}}
        />
    );
}

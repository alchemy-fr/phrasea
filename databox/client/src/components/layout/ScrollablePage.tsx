import type {ComponentProps} from 'react';
import {cn} from '@alchemy/next-ui/utils/cn';

/**
 * Centered content of a full page: the whole main area scrolls, so that the
 * scrollbar sits on the right edge of the screen rather than of the content.
 * `className` applies to the centered content (its max width).
 */
export function ScrollablePage({className, ...props}: ComponentProps<'div'>) {
    return (
        <div className="min-h-0 w-full flex-1 overflow-y-auto">
            <div
                className={cn('mx-auto w-full space-y-4 p-4', className)}
                {...props}
            />
        </div>
    );
}

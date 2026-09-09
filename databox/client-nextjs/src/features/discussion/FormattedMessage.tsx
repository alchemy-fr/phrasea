import {Fragment, ReactNode} from 'react';
import {truncate} from '@/lib/utils/format';

const tokenRegex = /(https?:\/\/[^\s<]+|@[\w.-]+)/g;

/**
 * Renders a plain-text message: mentions as pills, URLs as links (truncated
 * beyond 50 characters), line breaks preserved.
 */
export function FormattedMessage({content}: {content: string}): ReactNode {
    return (
        <span className="whitespace-pre-wrap break-words">
            {content.split(tokenRegex).map((part, i) => {
                if (/^https?:\/\//.test(part)) {
                    return (
                        <a
                            key={i}
                            href={part}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary underline-offset-2 hover:underline"
                        >
                            {truncate(part, 50)}
                        </a>
                    );
                }
                if (/^@[\w.-]+$/.test(part)) {
                    return (
                        <span
                            key={i}
                            className="rounded bg-primary/10 px-1 font-medium text-primary"
                        >
                            {part}
                        </span>
                    );
                }

                return <Fragment key={i}>{part}</Fragment>;
            })}
        </span>
    );
}

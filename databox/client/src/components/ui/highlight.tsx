import {Fragment, ReactNode} from 'react';

/**
 * Renders `[hl]…[/hl]` (API highlight tags) as <mark>.
 *
 * The parts are wrapped in a single <span>: in a flex parent, they would
 * otherwise each be an item, spaced by its gap even within a word.
 */
export function Highlight({
    text,
}: {
    text: string | null | undefined;
}): ReactNode {
    if (!text) {
        return null;
    }
    if (!text.includes('[hl]')) {
        return text;
    }
    const parts = text.split(/(\[hl\].*?\[\/hl\])/g);

    return (
        <span>
            {parts.map((part, i) => {
                const m = part.match(/^\[hl\](.*?)\[\/hl\]$/);

                return m ? (
                    <mark key={i}>{m[1]}</mark>
                ) : (
                    <Fragment key={i}>{part}</Fragment>
                );
            })}
        </span>
    );
}

export function stripHighlight(text: string | null | undefined): string {
    return (text ?? '').replace(/\[\/?hl\]/g, '');
}

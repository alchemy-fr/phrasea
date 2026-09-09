import {Fragment, ReactNode} from 'react';

/**
 * Renders `[hl]…[/hl]` (API highlight tags) as <mark>.
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
        <>
            {parts.map((part, i) => {
                const m = part.match(/^\[hl\](.*?)\[\/hl\]$/);

                return m ? (
                    <mark key={i}>{m[1]}</mark>
                ) : (
                    <Fragment key={i}>{part}</Fragment>
                );
            })}
        </>
    );
}

export function stripHighlight(text: string | null | undefined): string {
    return (text ?? '').replace(/\[\/?hl\]/g, '');
}

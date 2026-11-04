/**
 * Turns the HTML body of a notification into plain text: entities decoded,
 * tags removed, whitespace collapsed.
 *
 * The parsed document is never attached to the page, so its scripts and
 * event handlers never run.
 */
export function htmlToText(html: string): string {
    if (typeof DOMParser === 'undefined') {
        return html.replace(/<[^>]+>/g, '');
    }
    const doc = new DOMParser().parseFromString(html, 'text/html');

    return (doc.body.textContent ?? '').replace(/\s+/g, ' ').trim();
}

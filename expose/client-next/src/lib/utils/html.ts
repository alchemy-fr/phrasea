import DOMPurify from 'dompurify';

/**
 * The HTML written by the editors (descriptions, terms), without anything
 * that could run a script: tags, attributes and URLs are sanitized, links
 * open in a new tab. Empty on the server, which never renders API content.
 */
export function sanitizeHtml(html: string | null | undefined): string {
    if (!html || typeof window === 'undefined') {
        return '';
    }

    return DOMPurify.sanitize(html, {
        USE_PROFILES: {html: true},
        FORBID_TAGS: ['style', 'form', 'input', 'button', 'textarea', 'select'],
        ADD_ATTR: ['target'],
    });
}

let hooked = false;

/** Links of the sanitized HTML open in a new tab, without the opener */
export function installLinkHook(): void {
    if (hooked || typeof window === 'undefined') {
        return;
    }
    hooked = true;
    DOMPurify.addHook('afterSanitizeAttributes', node => {
        if (node.tagName === 'A' && node.getAttribute('href')) {
            node.setAttribute('target', '_blank');
            node.setAttribute('rel', 'noopener noreferrer');
        }
    });
}

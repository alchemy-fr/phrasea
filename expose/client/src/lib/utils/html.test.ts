import {describe, expect, it} from 'vitest';
import {installLinkHook, sanitizeHtml} from './html';

describe('sanitizeHtml', () => {
    it('keeps the formatting', () => {
        expect(sanitizeHtml('<p>Hello <b>world</b></p>')).toBe(
            '<p>Hello <b>world</b></p>'
        );
    });

    it('drops scripts, handlers and javascript URLs', () => {
        const html = sanitizeHtml(
            '<img src="x" onerror="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)">x</a>'
        );
        expect(html).not.toContain('onerror');
        expect(html).not.toContain('<script');
        expect(html).not.toContain('javascript:');
    });

    it('opens the links in a new tab', () => {
        installLinkHook();
        expect(sanitizeHtml('<a href="https://example.com">x</a>')).toBe(
            '<a href="https://example.com" target="_blank" rel="noopener noreferrer">x</a>'
        );
    });

    it('is empty for nothing', () => {
        expect(sanitizeHtml(null)).toBe('');
        expect(sanitizeHtml('')).toBe('');
    });
});

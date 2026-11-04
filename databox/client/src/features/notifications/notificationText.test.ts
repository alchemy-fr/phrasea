import {describe, expect, it} from 'vitest';
import {htmlToText} from './notificationText';

describe('htmlToText', () => {
    it('decodes the entities', () => {
        expect(htmlToText('Rendition &quot;preview&quot; not found')).toBe(
            'Rendition "preview" not found'
        );
        expect(htmlToText('Tom &amp; Jerry')).toBe('Tom & Jerry');
    });

    it('removes the tags', () => {
        expect(htmlToText('Alice added <strong>a.jpg</strong> to X.')).toBe(
            'Alice added a.jpg to X.'
        );
    });

    it('collapses the whitespace', () => {
        expect(htmlToText('<p>a</p>\n<p>b</p>')).toBe('a b');
    });

    it('never runs the markup', () => {
        const before = document.body.innerHTML;

        expect(
            htmlToText('<img src="x" onerror="window.__pwned = true">ok')
        ).toBe('ok');
        expect(Reflect.get(window, '__pwned')).toBeUndefined();
        expect(document.body.innerHTML).toBe(before);
    });
});

import {afterEach, describe, expect, it} from 'vitest';
import {focusableIn, hasFocusableInStop, nextFieldStop} from './fieldTabbing';

function build(html: string): HTMLElement {
    const root = document.createElement('div');
    root.innerHTML = html;
    document.body.appendChild(root);

    return root;
}

const byId = (id: string) => document.getElementById(id)!;

afterEach(() => {
    document.body.innerHTML = '';
});

describe('fieldTabbing', () => {
    const form = `
        <div data-attr-stop id="s1"><input id="a"></div>
        <button id="annex">Annotate</button>
        <div data-attr-stop id="s2"><input id="b" disabled></div>
        <div data-attr-stop id="s3"><textarea id="c"></textarea></div>
        <button data-attr-stop id="s4">Add</button>
    `;

    it('goes to the next stop, skipping a disabled one', () => {
        const root = build(form);

        expect(nextFieldStop(root, byId('a'), false)).toBe(byId('s3'));
        expect(nextFieldStop(root, byId('c'), false)).toBe(byId('s4'));
    });

    it('goes to the previous stop', () => {
        const root = build(form);

        expect(nextFieldStop(root, byId('s4'), true)).toBe(byId('s3'));
        expect(nextFieldStop(root, byId('c'), true)).toBe(byId('s1'));
    });

    it('starts from a button between two stops', () => {
        const root = build(form);

        expect(nextFieldStop(root, byId('annex'), false)).toBe(byId('s3'));
        expect(nextFieldStop(root, byId('annex'), true)).toBe(byId('s1'));
    });

    it('has nothing after the last stop nor before the first', () => {
        const root = build(form);

        expect(nextFieldStop(root, byId('s4'), false)).toBeUndefined();
        expect(nextFieldStop(root, byId('a'), true)).toBeUndefined();
    });

    it('finds the focusable element of a stop', () => {
        build(`
            <div id="s1"><input type="hidden"><button disabled>x</button><span tabindex="-1">y</span><button aria-disabled="true">z</button><input id="a"></div>
            <button id="s2">Add</button>
            <div id="s3"><input disabled></div>
        `);

        expect(focusableIn(byId('s1'))).toBe(byId('a'));
        expect(focusableIn(byId('s2'))).toBe(byId('s2'));
        expect(focusableIn(byId('s3'))).toBeUndefined();
    });

    it('tells whether the stop has another control to reach', () => {
        const root = build(`
            <div data-attr-stop><button id="swatch">color</button><input id="hex"></div>
            <div data-attr-stop><input id="single"></div>
        `);

        expect(hasFocusableInStop(root, byId('swatch'), false)).toBe(true);
        expect(hasFocusableInStop(root, byId('hex'), false)).toBe(false);
        expect(hasFocusableInStop(root, byId('hex'), true)).toBe(true);
        expect(hasFocusableInStop(root, byId('single'), false)).toBe(false);
    });
});

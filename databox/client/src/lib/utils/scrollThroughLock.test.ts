import {afterEach, describe, expect, it} from 'vitest';
import {scrollThroughLock} from './scrollThroughLock';

function scrollable(
    overflow: 'auto' | 'visible',
    size: {client: number; scroll: number}
): HTMLDivElement {
    const el = document.createElement('div');
    el.style.overflowY = overflow;
    el.style.overflowX = overflow;
    Object.defineProperty(el, 'clientHeight', {value: size.client});
    Object.defineProperty(el, 'scrollHeight', {value: size.scroll});
    Object.defineProperty(el, 'clientWidth', {value: size.client});
    Object.defineProperty(el, 'scrollWidth', {value: size.scroll});
    document.body.appendChild(el);

    return el;
}

function wheel(target: Element, init: WheelEventInit): WheelEvent {
    const e = new WheelEvent('wheel', {
        bubbles: true,
        cancelable: true,
        ...init,
    });
    target.dispatchEvent(e);

    return e;
}

function touchMove(
    target: Element,
    type: 'touchstart' | 'touchmove',
    x: number,
    y: number
): Event {
    const e = new Event(type, {bubbles: true, cancelable: true});
    Object.defineProperty(e, 'touches', {value: [{clientX: x, clientY: y}]});
    target.dispatchEvent(e);

    return e;
}

describe('scrollThroughLock', () => {
    afterEach(() => {
        document.body.removeAttribute('data-scroll-locked');
        document.body.innerHTML = '';
    });

    it('leaves the native scroll alone while the page is not locked', () => {
        const list = scrollable('auto', {client: 100, scroll: 500});
        scrollThroughLock(list);

        const e = wheel(list, {deltaY: 40});

        expect(e.defaultPrevented).toBe(false);
        expect(list.scrollTop).toBe(0);
    });

    it('scrolls by hand while a modal layer locks the page', () => {
        document.body.setAttribute('data-scroll-locked', '1');
        const list = scrollable('auto', {client: 100, scroll: 500});
        const item = document.createElement('div');
        list.appendChild(item);
        scrollThroughLock(list);

        expect(wheel(item, {deltaY: 40}).defaultPrevented).toBe(true);
        expect(list.scrollTop).toBe(40);

        wheel(item, {deltaY: 3, deltaMode: WheelEvent.DOM_DELTA_LINE});
        expect(list.scrollTop).toBe(40 + 3 * 16);

        wheel(item, {deltaY: -1000});
        expect(list.scrollTop).toBe(-1000 + 88); // jsdom does not clamp

        wheel(item, {deltaX: 20});
        expect(list.scrollLeft).toBe(20);
    });

    it('scrolls the innermost element that still can, then its ancestors', () => {
        document.body.setAttribute('data-scroll-locked', '1');
        const root = scrollable('auto', {client: 100, scroll: 500});
        const inner = scrollable('auto', {client: 50, scroll: 100});
        root.appendChild(inner);
        const still = scrollable('visible', {client: 50, scroll: 100});
        inner.appendChild(still);
        scrollThroughLock(root);

        wheel(still, {deltaY: 30});
        expect(inner.scrollTop).toBe(30);
        expect(root.scrollTop).toBe(0);

        inner.scrollTop = 50; // at the end
        wheel(still, {deltaY: 30});
        expect(inner.scrollTop).toBe(50);
        expect(root.scrollTop).toBe(30);

        wheel(still, {deltaY: -30}); // inner can scroll back up
        expect(inner.scrollTop).toBe(20);
        expect(root.scrollTop).toBe(30);
    });

    it('follows a single touch while locked', () => {
        document.body.setAttribute('data-scroll-locked', '1');
        const list = scrollable('auto', {client: 100, scroll: 500});
        scrollThroughLock(list);

        touchMove(list, 'touchstart', 10, 200);
        const e = touchMove(list, 'touchmove', 10, 170);

        expect(e.defaultPrevented).toBe(true);
        expect(list.scrollTop).toBe(30);

        touchMove(list, 'touchmove', 10, 160);
        expect(list.scrollTop).toBe(40);
    });

    it('stops listening once cleaned up', () => {
        document.body.setAttribute('data-scroll-locked', '1');
        const list = scrollable('auto', {client: 100, scroll: 500});
        const stop = scrollThroughLock(list);
        stop?.();

        expect(wheel(list, {deltaY: 40}).defaultPrevented).toBe(false);
        expect(list.scrollTop).toBe(0);
        expect(scrollThroughLock(null)).toBeUndefined();
    });
});

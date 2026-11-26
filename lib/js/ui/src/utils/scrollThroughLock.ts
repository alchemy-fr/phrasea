/** Pixels per line for wheel events measured in lines (Firefox) */
const LINE_HEIGHT = 16;

/**
 * Whether a modal layer (Dialog, Sheet…) has locked the page scroll:
 * react-remove-scroll then flags the body and cancels the wheel and touch
 * events of everything outside the layer.
 */
function isPageScrollLocked(): boolean {
    return document.body.hasAttribute('data-scroll-locked');
}

/**
 * Keeps the content of `root` scrollable while the page scroll is locked by a
 * modal layer: a content portaled to the body (a Popover opened from a
 * dialog) is outside the layer, so the lock cancels its wheel and touch
 * events. This scrolls the innermost scrollable element under the pointer by
 * hand instead. Usable as a ref callback (returns the cleanup).
 */
export function scrollThroughLock(
    root: HTMLElement | null
): (() => void) | undefined {
    if (!root) {
        return;
    }
    let touch: {x: number; y: number} | null = null;

    const onWheel = (e: WheelEvent) => {
        if (!isPageScrollLocked()) {
            return;
        }
        e.preventDefault();
        const unit =
            e.deltaMode === WheelEvent.DOM_DELTA_LINE
                ? LINE_HEIGHT
                : e.deltaMode === WheelEvent.DOM_DELTA_PAGE
                  ? root.clientHeight
                  : 1;
        scrollBy(root, e.target, e.deltaX * unit, e.deltaY * unit);
    };
    const onTouchStart = (e: TouchEvent) => {
        const t = e.touches[0];
        touch =
            t && e.touches.length === 1 ? {x: t.clientX, y: t.clientY} : null;
    };
    const onTouchMove = (e: TouchEvent) => {
        const t = e.touches[0];
        if (!touch || !t || e.touches.length !== 1 || !isPageScrollLocked()) {
            return;
        }
        e.preventDefault();
        scrollBy(root, e.target, touch.x - t.clientX, touch.y - t.clientY);
        touch = {x: t.clientX, y: t.clientY};
    };

    root.addEventListener('wheel', onWheel, {passive: false});
    root.addEventListener('touchstart', onTouchStart, {passive: true});
    root.addEventListener('touchmove', onTouchMove, {passive: false});

    return () => {
        root.removeEventListener('wheel', onWheel);
        root.removeEventListener('touchstart', onTouchStart);
        root.removeEventListener('touchmove', onTouchMove);
    };
}

/**
 * Scrolls, from `target` up to `root`, the innermost element that can still
 * scroll in the direction of each delta (as the native scroll chaining does).
 */
function scrollBy(
    root: HTMLElement,
    target: EventTarget | null,
    dx: number,
    dy: number
): void {
    let el: Element | null =
        target instanceof Element && root.contains(target) ? target : root;
    while (el && (dx || dy)) {
        if (dy && canScroll(el, 'y', dy)) {
            el.scrollTop += dy;
            dy = 0;
        }
        if (dx && canScroll(el, 'x', dx)) {
            el.scrollLeft += dx;
            dx = 0;
        }
        el = el === root ? null : el.parentElement;
    }
}

function canScroll(el: Element, axis: 'x' | 'y', delta: number): boolean {
    const style = getComputedStyle(el);
    const overflow = axis === 'y' ? style.overflowY : style.overflowX;
    if (overflow !== 'auto' && overflow !== 'scroll') {
        return false;
    }
    if (axis === 'y') {
        return delta < 0
            ? el.scrollTop > 0
            : el.scrollTop + el.clientHeight < el.scrollHeight - 1;
    }

    return delta < 0
        ? el.scrollLeft > 0
        : el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
}

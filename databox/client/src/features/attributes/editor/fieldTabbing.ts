/**
 * Keyboard navigation between the fields of a form: elements marked with
 * `FIELD_STOP_ATTR` wrap the input(s) of one value, and Tab moves from one
 * of them to the next, skipping whatever lies between (secondary buttons).
 */
export const FIELD_STOP_ATTR = 'data-attr-stop';

const FOCUSABLE =
    'input, textarea, select, button, [role=combobox], [tabindex]:not([tabindex="-1"])';

function isFocusable(el: HTMLElement): boolean {
    return (
        !(el as HTMLInputElement).disabled &&
        el.getAttribute('aria-disabled') !== 'true' &&
        !(el instanceof HTMLInputElement && el.type === 'hidden') &&
        el.tabIndex >= 0 &&
        !el.closest('[aria-hidden="true"], [inert]')
    );
}

function focusablesIn(stop: HTMLElement): HTMLElement[] {
    const inner = Array.from(stop.querySelectorAll<HTMLElement>(FOCUSABLE));

    return (stop.matches(FOCUSABLE) ? [stop, ...inner] : inner).filter(
        isFocusable
    );
}

/** First enabled element of the stop that can take the focus. */
export function focusableIn(stop: HTMLElement): HTMLElement | undefined {
    return focusablesIn(stop)[0];
}

function stopOf(root: HTMLElement, el: Element): HTMLElement | undefined {
    const stop = el.closest<HTMLElement>(`[${FIELD_STOP_ATTR}]`);

    return stop && root.contains(stop) ? stop : undefined;
}

/**
 * Whether Tab from `from` has another element to reach inside its own stop,
 * as in a widget made of several controls.
 */
export function hasFocusableInStop(
    root: HTMLElement,
    from: Element,
    back: boolean
): boolean {
    const stop = stopOf(root, from);
    if (!stop) {
        return false;
    }
    const list = focusablesIn(stop);
    const i = list.findIndex(el => el === from || el.contains(from));

    return i >= 0 && (back ? i > 0 : i < list.length - 1);
}

/**
 * The stop after (or before, when `back`) the one of `from`, among the stops
 * of `root` having an enabled focusable element. When `from` is in no stop,
 * its position in the document decides.
 */
export function nextFieldStop(
    root: HTMLElement,
    from: Element,
    back: boolean
): HTMLElement | undefined {
    const stops = Array.from(
        root.querySelectorAll<HTMLElement>(`[${FIELD_STOP_ATTR}]`)
    ).filter(s => focusableIn(s));

    const current = stopOf(root, from);
    const index = current ? stops.indexOf(current) : -1;
    if (index >= 0) {
        return stops[back ? index - 1 : index + 1];
    }

    if (back) {
        return stops.findLast(
            s =>
                !s.contains(from) &&
                (s.compareDocumentPosition(from) &
                    Node.DOCUMENT_POSITION_FOLLOWING) !==
                    0
        );
    }

    return stops.find(
        s =>
            !s.contains(from) &&
            (from.compareDocumentPosition(s) &
                Node.DOCUMENT_POSITION_FOLLOWING) !==
                0
    );
}

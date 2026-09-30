'use client';

import {
    createContext,
    ReactNode,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import {cn} from '@/lib/utils/cn';
import {moveSidebarSection} from './sidebarSections';

/** Given by `SortableSections` to the `PanelSection` it wraps: the grip moving it */
export type SectionGrip = {
    id: string;
    onPointerDown: (e: ReactPointerEvent) => void;
    onKeyDown: (e: ReactKeyboardEvent) => void;
    dragging: boolean;
};

const SectionGripContext = createContext<SectionGrip | null>(null);

export function useSectionGrip(): SectionGrip | null {
    return useContext(SectionGripContext);
}

type Drag = {
    id: string;
    /** Where the section would land if dropped now */
    to: number;
    x: number;
    y: number;
};

/**
 * Panel sections the user reorders by dragging the grip of their header (see
 * `PanelSection`), or with the arrow keys once the grip is focused.
 *
 * Plain pointer events rather than dnd-kit: the sections hold the drop
 * targets of the app-wide drag & drop, which a nested `DndContext` would
 * capture. The sections do not move while dragged — they are much taller
 * than their header — a line marks where the dragged one will land.
 */
export function SortableSections<T extends string>({
    order,
    onReorder,
    sections,
    labels,
}: {
    order: T[];
    onReorder: (order: T[]) => void;
    sections: Record<T, ReactNode>;
    /** Shown next to the pointer while dragging */
    labels: Record<T, ReactNode>;
}) {
    const nodes = useRef(new Map<string, HTMLElement>());
    const [drag, setDrag] = useState<Drag>();
    const orderRef = useRef(order);
    orderRef.current = order;
    // The grip moved with the keyboard: focused again once re-rendered
    const [refocus, setRefocus] = useState<string>();

    useEffect(() => {
        if (!refocus) {
            return;
        }
        nodes.current
            .get(refocus)
            ?.querySelector<HTMLElement>('[data-section-grip]')
            ?.focus();
        setRefocus(undefined);
    }, [refocus, order]);

    /** Index of the section under the pointer (sections rendering nothing are skipped) */
    const indexAt = useCallback((y: number, fallback: number): number => {
        const list = orderRef.current;
        let last = fallback;
        for (let i = 0; i < list.length; i++) {
            const rect = nodes.current.get(list[i])?.getBoundingClientRect();
            if (!rect || rect.height === 0) {
                continue;
            }
            if (y < rect.bottom) {
                return i;
            }
            last = i;
        }

        return last;
    }, []);

    const startDrag = useCallback(
        (id: T) => (e: ReactPointerEvent) => {
            if (e.button !== 0) {
                return;
            }
            e.preventDefault();
            try {
                (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
            } catch {
                // no such pointer (synthetic event)
            }
            const from = orderRef.current.indexOf(id);
            let current: Drag = {id, to: from, x: e.clientX, y: e.clientY};
            setDrag(current);

            const move = (ev: PointerEvent) => {
                current = {
                    id,
                    to: indexAt(ev.clientY, current.to),
                    x: ev.clientX,
                    y: ev.clientY,
                };
                setDrag(current);
            };
            const stop = (drop: boolean) => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
                window.removeEventListener('pointercancel', cancel);
                window.removeEventListener('keydown', escape, true);
                setDrag(undefined);
                if (drop && current.to !== from) {
                    onReorder(
                        moveSidebarSection(orderRef.current, from, current.to)
                    );
                }
            };
            const up = () => stop(true);
            const cancel = () => stop(false);
            const escape = (ev: KeyboardEvent) => {
                if (ev.key === 'Escape') {
                    ev.preventDefault();
                    ev.stopPropagation();
                    stop(false);
                }
            };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
            window.addEventListener('pointercancel', cancel);
            window.addEventListener('keydown', escape, true);
        },
        [indexAt, onReorder]
    );

    const moveWithKeys = useCallback(
        (id: T) => (e: ReactKeyboardEvent) => {
            const offset =
                e.key === 'ArrowUp' ? -1 : e.key === 'ArrowDown' ? 1 : 0;
            if (!offset) {
                return;
            }
            e.preventDefault();
            const from = orderRef.current.indexOf(id);
            const to = from + offset;
            if (to < 0 || to >= orderRef.current.length) {
                return;
            }
            onReorder(moveSidebarSection(orderRef.current, from, to));
            setRefocus(id);
        },
        [onReorder]
    );

    const from = drag ? order.indexOf(drag.id as T) : -1;

    return (
        <>
            {order.map((id, i) => (
                <div
                    key={id}
                    ref={el => {
                        if (el) {
                            nodes.current.set(id, el);
                        } else {
                            nodes.current.delete(id);
                        }
                    }}
                    data-testid="sortable-section"
                    data-section-id={id}
                    className={cn(
                        'relative',
                        drag?.id === id && 'opacity-50',
                        // Where the dragged section lands: above the
                        // section when moved up, below it when moved down
                        drag &&
                            drag.to === i &&
                            i !== from &&
                            cn(
                                'after:pointer-events-none after:absolute after:inset-x-0 after:z-20 after:h-0.5 after:bg-primary',
                                i < from ? 'after:top-0' : 'after:bottom-0'
                            )
                    )}
                >
                    <SectionGripContext.Provider
                        value={{
                            id,
                            onPointerDown: startDrag(id),
                            onKeyDown: moveWithKeys(id),
                            dragging: drag?.id === id,
                        }}
                    >
                        {sections[id]}
                    </SectionGripContext.Provider>
                </div>
            ))}
            {drag ? (
                <div
                    className="pointer-events-none fixed z-50 rounded border bg-popover px-2 py-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase shadow-lg"
                    style={{left: drag.x + 12, top: drag.y + 8}}
                >
                    {labels[drag.id as T]}
                </div>
            ) : null}
        </>
    );
}

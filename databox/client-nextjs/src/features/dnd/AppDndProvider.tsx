'use client';

import {
    PropsWithChildren,
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';
import {
    DndContext,
    DragOverlay,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragOverEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import {useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {useOptionalResults} from '@/features/search/useOptionalResults';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {
    DndEnabledContext,
    DragOverContext,
    DragPayloadContext,
    emptyDragOver,
    type DragOverState,
} from './DragContext';
import {assetKey} from '@/features/assets/list/SelectionProvider';
import {DragGhost} from './DragGhost';
import {canDrop} from './canDrop';
import {mostSpecificPointerWithin} from './collision';
import {executeDrop} from './executeDrop';
import {snapToCursor} from './snapToCursor';
import {
    type DragModifiers,
    type DragPayload,
    type DragSource,
    type DropTarget,
    type DropVerdict,
    isNeutralTarget,
    noModifiers,
} from './types';

/**
 * The drag & drop layer of the app: assets dragged from a list onto the
 * sidebar (collections, workspaces, baskets, pinned stories), collections
 * moved within the tree. Sits above both the sidebar and the screens, which
 * are siblings in the shell.
 *
 * The payload is resolved once at drag start and shared through
 * `DragPayloadContext`; what the pointer is over, with the verdict for the
 * modifier keys held, goes through `DragOverContext`.
 */
export function AppDndProvider({children}: PropsWithChildren) {
    const {status} = useAuth();
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const {openModal} = useModals();
    const results = useOptionalResults();
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 8}})
    );
    const [payload, setPayload] = useState<DragPayload | null>(null);
    const [over, setOver] = useState<DragOverState>(emptyDragOver);
    const payloadRef = useRef<DragPayload | null>(null);
    const targetRef = useRef<DropTarget | null>(null);
    const modifiersRef = useRef<DragModifiers>(noModifiers);

    const evaluate = useCallback(
        (target: DropTarget | null): DropVerdict | null =>
            payloadRef.current && target && !isNeutralTarget(target)
                ? canDrop(payloadRef.current, target, {
                      collections: useCollectionStore.getState().collections,
                      modifiers: modifiersRef.current,
                  })
                : null,
        []
    );
    const refresh = useCallback(() => {
        const target = targetRef.current;
        const verdict = evaluate(target);
        setOver({target, verdict, modifiers: modifiersRef.current});
        if (verdict) {
            document.body.dataset.drop = verdict.ok ? 'valid' : 'invalid';
        } else {
            delete document.body.dataset.drop;
        }
    }, [evaluate]);

    const reset = useCallback(() => {
        payloadRef.current = null;
        targetRef.current = null;
        modifiersRef.current = noModifiers;
        setPayload(null);
        setOver(emptyDragOver);
        delete document.body.dataset.dragging;
        delete document.body.dataset.drop;
    }, []);

    // Modifier keys are read from the keyboard and pointer events of the
    // drag: dnd-kit's own events do not carry them
    useEffect(() => {
        if (!payload) {
            return;
        }
        const update = (e: KeyboardEvent | PointerEvent) => {
            const next = {shift: e.shiftKey, ctrl: e.ctrlKey || e.metaKey};
            const current = modifiersRef.current;
            if (next.shift !== current.shift || next.ctrl !== current.ctrl) {
                modifiersRef.current = next;
                refresh();
            }
        };
        window.addEventListener('keydown', update);
        window.addEventListener('keyup', update);
        window.addEventListener('pointermove', update);

        return () => {
            window.removeEventListener('keydown', update);
            window.removeEventListener('keyup', update);
            window.removeEventListener('pointermove', update);
        };
    }, [payload, refresh]);

    const onDragStart = (e: DragStartEvent) => {
        const source = e.active.data.current as DragSource | undefined;
        const resolved = resolvePayload(source);
        if (!resolved) {
            return;
        }
        payloadRef.current = resolved;
        const activator = e.activatorEvent as PointerEvent | undefined;
        modifiersRef.current = activator
            ? {
                  shift: !!activator.shiftKey,
                  ctrl: !!activator.ctrlKey || !!activator.metaKey,
              }
            : noModifiers;
        setPayload(resolved);
        setOver({...emptyDragOver, modifiers: modifiersRef.current});
        document.body.dataset.dragging = resolved.type;
    };

    const onDragOver = (e: DragOverEvent) => {
        targetRef.current =
            (e.over?.data.current as DropTarget | undefined) ?? null;
        refresh();
    };

    const onDragEnd = (e: DragEndEvent) => {
        const dragged = payloadRef.current;
        const target = (e.over?.data.current as DropTarget | undefined) ?? null;
        const verdict = evaluate(target);
        reset();
        if (dragged && target && verdict?.ok) {
            void executeDrop(dragged, target, verdict.op, {
                t,
                queryClient,
                reloadResults: results ? () => results.reload() : undefined,
                confirm: async options =>
                    (await openModal(ConfirmDialog, options)) === true,
            });
        }
    };

    return (
        <DndEnabledContext.Provider value={status === 'authenticated'}>
            <DragPayloadContext.Provider value={payload}>
                <DragOverContext.Provider value={over}>
                    <DndContext
                        sensors={sensors}
                        collisionDetection={mostSpecificPointerWithin}
                        autoScroll={{
                            canScroll: el => el.hasAttribute('data-dnd-scroll'),
                            threshold: {x: 0, y: 0.2},
                        }}
                        onDragStart={onDragStart}
                        onDragOver={onDragOver}
                        onDragEnd={onDragEnd}
                        onDragCancel={reset}
                    >
                        {children}
                        <DragOverlay
                            dropAnimation={null}
                            modifiers={[snapToCursor]}
                            zIndex={60}
                            style={{width: 'auto', height: 'auto'}}
                        >
                            <DragGhost />
                        </DragOverlay>
                    </DndContext>
                </DragOverContext.Provider>
            </DragPayloadContext.Provider>
        </DndEnabledContext.Provider>
    );
}

/**
 * A selected card takes the whole selection along; an unselected one goes
 * alone and leaves the selection as it is.
 */
export function resolvePayload(
    source: DragSource | undefined
): DragPayload | null {
    if (!source) {
        return null;
    }
    if (source.kind === 'collection-source') {
        return {type: 'collection', collection: source.collection};
    }
    const selection = source.getSelection();
    const assets = (
        selection.some(a => assetKey(a) === assetKey(source.asset))
            ? selection
            : [source.asset]
    ).filter(a => !a.deleted);
    if (assets.length === 0) {
        return null;
    }

    return {
        type: 'assets',
        assets,
        source: {scope: source.scope, basketId: source.basketId},
    };
}

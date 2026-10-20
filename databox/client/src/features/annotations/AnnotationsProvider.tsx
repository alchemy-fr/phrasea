'use client';

import {
    createContext,
    ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type {AssetAnnotation} from './types';

/**
 * What the shapes drawn over the media belong to: the message being written,
 * a posted message, a value of an attribute… One target at a time is shown
 * by the player; an editable one gets the drawing tools.
 */
export type AnnotationTarget = {
    /** Identifies the annotated thing, so that its owner knows it is shown */
    key: string;
    annotations: AssetAnnotation[];
    /** Shapes may be drawn, moved, renamed and deleted */
    editable?: boolean;
    /** The shape to highlight first */
    selectedId?: string;
    /** Receives the shapes (without their local flags) after every edit */
    onChange?: (annotations: AssetAnnotation[]) => void;
    /** Shown next to the tools: what is being annotated */
    label?: string;
};

type AnnotationsContextValue = {
    target: AnnotationTarget | undefined;
    setTarget: (target: AnnotationTarget | undefined) => void;
    /** Drops the target when it is the given one (or any when omitted) */
    clearTarget: (key?: string) => void;
    /** The displayed media can show annotations (an image is on screen) */
    supported: boolean;
    setSupported: (supported: boolean) => void;
};

const AnnotationsContext = createContext<AnnotationsContextValue | undefined>(
    undefined
);

/**
 * Shares the annotations shown by the player of an asset view with the
 * side panel (discussion, attributes, integrations). `resetKey` (the asset)
 * drops the target when it changes.
 */
export function AnnotationsProvider({
    children,
    resetKey,
}: {
    children: ReactNode;
    resetKey?: string;
}) {
    const [target, setTarget] = useState<AnnotationTarget>();
    const [supported, setSupported] = useState(false);

    useEffect(() => {
        setTarget(undefined);
    }, [resetKey]);

    const clearTarget = useCallback((key?: string) => {
        setTarget(t => (key === undefined || t?.key === key ? undefined : t));
    }, []);

    const value = useMemo<AnnotationsContextValue>(
        () => ({target, setTarget, clearTarget, supported, setSupported}),
        [target, clearTarget, supported]
    );

    return (
        <AnnotationsContext.Provider value={value}>
            {children}
        </AnnotationsContext.Provider>
    );
}

/** Undefined outside of an asset view (players elsewhere show no annotation) */
export function useAnnotations(): AnnotationsContextValue | undefined {
    return useContext(AnnotationsContext);
}

/**
 * Lets a component own the annotation target: `activate()` shows its shapes
 * on the player, which stay in sync with `props` while it is active; the
 * target is dropped when the component unmounts.
 */
export function useAnnotationTarget(
    key: string,
    props: Omit<AnnotationTarget, 'key'>
) {
    const ctx = useAnnotations();
    const ctxRef = useRef(ctx);
    ctxRef.current = ctx;
    const active = ctx?.target?.key === key;
    const {annotations, editable, selectedId, label} = props;
    // The callback may be recreated on every render of the owner: the target
    // is not replaced for that (it would re-render the owner, and so on)
    const onChangeRef = useRef(props.onChange);
    onChangeRef.current = props.onChange;
    const onChange = useCallback(
        (next: AssetAnnotation[]) => onChangeRef.current?.(next),
        []
    );

    useEffect(() => {
        const c = ctxRef.current;
        if (c && c.target?.key === key) {
            c.setTarget({
                key,
                annotations,
                editable,
                selectedId,
                onChange,
                label,
            });
        }
    }, [key, annotations, editable, selectedId, onChange, label]);

    useEffect(
        () => () => {
            ctxRef.current?.clearTarget(key);
        },
        [key]
    );

    const activate = useCallback(
        (overrides?: Partial<AnnotationTarget>) =>
            ctxRef.current?.setTarget({
                key,
                annotations,
                editable,
                selectedId,
                onChange,
                label,
                ...overrides,
            }),
        [key, annotations, editable, selectedId, onChange, label]
    );
    const deactivate = useCallback(
        () => ctxRef.current?.clearTarget(key),
        [key]
    );

    return {
        /** No provider around, or the media cannot show annotations */
        supported: !!ctx?.supported,
        active,
        activate,
        deactivate,
        toggle: active ? deactivate : () => activate(),
    };
}

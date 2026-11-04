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
import {getAnnotationSets, type AnnotationSet} from './assetAnnotations';

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
    /**
     * The stored attribute value the shapes are of: while they are edited,
     * the other shapes of the asset stay shown around them, not its own
     */
    attributeId?: string;
    /**
     * Read only target: what replaces the name of the selected shape in its
     * floating panel (a picker of its value…); nothing keeps the name
     */
    renderControl?: (annotation: AssetAnnotation) => ReactNode;
};

/** Opens the shape at that position among the shapes of a value, to edit it */
export type ShapeEditor = (index: number) => void;

type AnnotationsContextValue = {
    /** The explicit target, else the fallback when shown */
    target: AnnotationTarget | undefined;
    setTarget: (target: AnnotationTarget | undefined) => void;
    /** Drops the target when it is the given one (or any when omitted) */
    clearTarget: (key?: string) => void;
    /** The displayed media can show annotations (an image is on screen) */
    supported: boolean;
    setSupported: (supported: boolean) => void;
    /** The shapes shown when nothing else is (the ones of the asset) */
    fallback: AnnotationTarget | undefined;
    showFallback: boolean;
    setShowFallback: (show: boolean) => void;
    /** The attributes the shapes of the asset belong to */
    sets: AnnotationSet[];
    /** The one the asset shows the shapes of, all of them when undefined */
    setId: string | undefined;
    /** Shows the shapes of the asset, of that attribute only when given */
    showSet: (setId: string | undefined) => void;
    /**
     * The editor of a stored attribute value takes the clicks on its shapes
     * shown with the asset (see `AssetAnnotation.source`)
     */
    registerShapeEditor: (
        attributeId: string,
        editor: ShapeEditor
    ) => () => void;
    /** Opens the shape in the editor of its value: false when there is none */
    editShape: (annotation: AssetAnnotation) => boolean;
    /** The value of the shape has an editor to open it */
    canEditShape: (annotation: AssetAnnotation) => boolean;
};

const AnnotationsContext = createContext<AnnotationsContextValue | undefined>(
    undefined
);

const showFallbackStorageKey = 'databox.annotations.show';

/**
 * Shares the annotations shown by the player of an asset view with the
 * side panel (discussion, attributes, integrations). `resetKey` (the asset)
 * drops the target when it changes. `fallback` (the shapes of the asset,
 * read only) is shown while no component owns the target, unless the user
 * hid it (remembered in the browser).
 */
export function AnnotationsProvider({
    children,
    resetKey,
    fallback,
}: {
    children: ReactNode;
    resetKey?: string;
    fallback?: AnnotationTarget;
}) {
    const [target, setTarget] = useState<AnnotationTarget>();
    const [supported, setSupported] = useState(false);
    const [showFallback, setShowFallbackState] = useState(true);
    // Kept from an asset to the next one
    const [chosenSetId, setChosenSetId] = useState<string>();

    useEffect(() => {
        setTarget(undefined);
    }, [resetKey]);

    // Read after the mount: the server does not know the choice
    useEffect(() => {
        try {
            setShowFallbackState(
                localStorage.getItem(showFallbackStorageKey) !== '0'
            );
        } catch {
            // storage unavailable
        }
    }, []);

    const setShowFallback = useCallback((show: boolean) => {
        setShowFallbackState(show);
        try {
            localStorage.setItem(showFallbackStorageKey, show ? '1' : '0');
        } catch {
            // ignore (private mode)
        }
    }, []);

    const sets = useMemo(
        () => getAnnotationSets(fallback?.annotations ?? []),
        [fallback]
    );
    // An asset without the shapes of the attribute chosen shows them all
    const setId = sets.some(s => s.id === chosenSetId)
        ? chosenSetId
        : undefined;
    const shownFallback = useMemo(() => {
        const set = sets.find(s => s.id === setId);
        if (!fallback || !set) {
            return fallback;
        }

        return {
            ...fallback,
            annotations: fallback.annotations.filter(
                a => a.source?.definitionId === set.id
            ),
            label: set.label,
        };
    }, [fallback, sets, setId]);
    const showSet = useCallback(
        (id: string | undefined) => {
            setChosenSetId(id);
            setTarget(undefined);
            setShowFallback(true);
        },
        [setShowFallback]
    );

    const clearTarget = useCallback((key?: string) => {
        setTarget(t => (key === undefined || t?.key === key ? undefined : t));
    }, []);

    // Read on click only: registering re-renders nothing
    const shapeEditors = useRef(new Map<string, ShapeEditor>());
    const registerShapeEditor = useCallback(
        (attributeId: string, editor: ShapeEditor) => {
            shapeEditors.current.set(attributeId, editor);

            return () => {
                if (shapeEditors.current.get(attributeId) === editor) {
                    shapeEditors.current.delete(attributeId);
                }
            };
        },
        []
    );
    const canEditShape = useCallback(
        (annotation: AssetAnnotation) =>
            !!annotation.source &&
            shapeEditors.current.has(annotation.source.attributeId),
        []
    );
    const editShape = useCallback((annotation: AssetAnnotation) => {
        const source = annotation.source;
        const editor = source && shapeEditors.current.get(source.attributeId);
        if (!editor) {
            return false;
        }
        editor(source.index);

        return true;
    }, []);

    const value = useMemo<AnnotationsContextValue>(
        () => ({
            target:
                target ??
                (showFallback && shownFallback?.annotations.length
                    ? shownFallback
                    : undefined),
            setTarget,
            clearTarget,
            supported,
            setSupported,
            fallback,
            showFallback,
            setShowFallback,
            sets,
            setId,
            showSet,
            registerShapeEditor,
            editShape,
            canEditShape,
        }),
        [
            target,
            clearTarget,
            supported,
            fallback,
            shownFallback,
            showFallback,
            setShowFallback,
            sets,
            setId,
            showSet,
            registerShapeEditor,
            editShape,
            canEditShape,
        ]
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
    const {annotations, editable, selectedId, label, attributeId} = props;
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
                // The shape given to `activate()` stays the one to highlight
                selectedId: selectedId ?? c.target.selectedId,
                onChange,
                label,
                attributeId,
            });
        }
    }, [key, annotations, editable, selectedId, onChange, label, attributeId]);

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
                attributeId,
                ...overrides,
            }),
        [key, annotations, editable, selectedId, onChange, label, attributeId]
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

const ShapeEditorsEnabled = createContext(true);

/**
 * The editors inside take the clicks on the shapes of the asset only while
 * `enabled`: an editor kept mounted while hidden must not.
 */
export function ShapeEditorsScope({
    enabled,
    children,
}: {
    enabled: boolean;
    children: ReactNode;
}) {
    return (
        <ShapeEditorsEnabled.Provider value={enabled}>
            {children}
        </ShapeEditorsEnabled.Provider>
    );
}

/**
 * Makes `editor` open the shapes of the stored attribute value shown with the
 * asset when they are clicked; nothing without a value or an editor.
 */
export function useShapeEditor(
    attributeId: string | undefined,
    editor: ShapeEditor | undefined
) {
    const register = useAnnotations()?.registerShapeEditor;
    const enabled = useContext(ShapeEditorsEnabled);
    const editorRef = useRef(editor);
    editorRef.current = editor;
    const hasEditor = !!editor;

    useEffect(() => {
        if (!register || !enabled || !attributeId || !hasEditor) {
            return;
        }

        return register(attributeId, index => editorRef.current?.(index));
    }, [register, enabled, attributeId, hasEditor]);
}

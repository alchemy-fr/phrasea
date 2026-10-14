'use client';

import {
    ReactNode,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import {shortId} from '@/lib/utils/misc';
import {useAnnotationDraw} from './useAnnotationDraw';
import {useAnnotationRender} from './useAnnotationRender';
import {
    AnnotationOptions,
    AnnotationsControl,
    AnnotationType,
    AssetAnnotation,
    OnNewAnnotation,
    ZoomRef,
} from './types';
import {AnnotationToolbar} from './AnnotationToolbar';
import {ShapeControl} from './ShapeControl';
import {getDrawingHandler} from './handlers';
import {annotationZIndex} from './controls';
import {ensureAnnotationIds, toStoredAnnotation} from './common';
import {getDefaultAnnotationName} from './annotationNames';
import {useAnnotations, type AnnotationTarget} from './AnnotationsProvider';

type Props = {
    /** Screen pixels per canvas pixel, read at render time */
    zoomRef: ZoomRef;
    /** Changes when the canvas must be redrawn (zoom step) */
    renderKey: unknown;
    page?: number;
};

const noAnnotations: AssetAnnotation[] = [];

function isTyping(): boolean {
    const el = document.activeElement as HTMLElement | null;

    return (
        !!el &&
        (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) ||
            el.isContentEditable)
    );
}

/**
 * The annotation layer of an image player: a canvas over the image, drawn
 * with the shapes of the current target of the {@link AnnotationsProvider},
 * and the tools to draw new ones when the target is editable.
 *
 * Returns nothing to render without a provider or a target.
 */
export function useAnnotationLayer({zoomRef, renderKey, page}: Props): {
    canvas: ReactNode;
    toolbar: ReactNode;
    /** The pointer draws or selects shapes instead of panning */
    active: boolean;
    target: AnnotationTarget | undefined;
} {
    const {t} = useTranslation();
    const ctx = useAnnotations();
    const target = ctx?.target;
    const editable = !!target?.editable;
    const selectedAnnotationRef = useRef<AssetAnnotation | undefined>(
        undefined
    );
    const shapeControlRef = useRef<HTMLDivElement | null>(null);
    const spaceRef = useRef<boolean>(false);
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const [mode, setMode] = useState<AnnotationType | undefined>(undefined);
    const [annotate, setAnnotate] = useState(true);
    const [options, setOptions] = useState<AnnotationOptions>({
        color: '#000000',
        size: 2,
    });
    const [, forceRender] = useState(0);

    const annotations = useMemo<AssetAnnotation[]>(
        () =>
            ensureAnnotationIds(
                target?.annotations ?? noAnnotations,
                target?.key ?? 'annotation'
            ).map(a => ({...a, editable})),
        [target?.annotations, target?.key, editable]
    );

    // The player tells the panel that annotations can be shown
    useEffect(() => {
        if (!ctx) {
            return;
        }
        ctx.setSupported(true);

        return () => ctx.setSupported(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ctx?.setSupported]);

    // Another target: the tools start afresh, its shape to highlight is selected
    const targetKey = target?.key;
    const selectedId = target?.selectedId;
    useEffect(() => {
        setMode(undefined);
        setAnnotate(true);
    }, [targetKey]);
    useEffect(() => {
        selectedAnnotationRef.current = selectedId
            ? annotations.find(a => a.id === selectedId)
            : undefined;
        forceRender(n => n + 1);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [targetKey, selectedId]);

    // The selection follows the shape through the updates of the list
    const sa = selectedAnnotationRef.current;
    if (sa) {
        if (!annotations.includes(sa)) {
            selectedAnnotationRef.current = annotations.find(
                a => a.id === sa.id
            );
        }
    }

    const active = !!target && editable && annotate;

    const onChange = target?.onChange;
    const emit = useCallback(
        (next: AssetAnnotation[]) => {
            onChange?.(next.map(toStoredAnnotation));
        },
        [onChange]
    );

    const {render} = useAnnotationRender({
        canvasRef,
        annotations,
        page,
        renderKey,
        zoomRef,
        selectedAnnotationRef,
        shapeControlRef,
    });

    const addAnnotation = useCallback<OnNewAnnotation>(
        annotation => {
            const created: AssetAnnotation = {
                ...annotation,
                id: `annotation-${shortId()}`,
                editable: true,
                page,
            };
            created.name ??= getDefaultAnnotationName(t, created, annotations);
            emit([...annotations, created]);
        },
        [t, emit, annotations, page]
    );

    const annotationsControl = useMemo<AnnotationsControl>(
        () => ({
            render,
            addAnnotation,
            updateAnnotation: (id, newAnnotation) => {
                if (!newAnnotation.editable) {
                    return newAnnotation;
                }
                emit(annotations.map(a => (a.id === id ? newAnnotation : a)));

                return newAnnotation;
            },
            deleteAnnotation: id => {
                if (selectedAnnotationRef.current?.id === id) {
                    selectedAnnotationRef.current = undefined;
                }
                emit(annotations.filter(a => a.id !== id));
            },
            selectAnnotation: annotation => {
                selectedAnnotationRef.current = annotation;
                render();
            },
        }),
        [render, addAnnotation, emit, annotations]
    );

    const onShapeDelete = useCallback(() => {
        const selected = selectedAnnotationRef.current;
        if (selected?.editable && selected.id) {
            annotationsControl.deleteAnnotation(selected.id);
        }
    }, [annotationsControl]);

    const onShapeDuplicate = useCallback(() => {
        const annotation = selectedAnnotationRef.current;
        if (annotation?.editable) {
            addAnnotation({...annotation, name: undefined});
        }
    }, [addAnnotation]);

    const onRename = useCallback(
        (newName: string) => {
            const annotation = selectedAnnotationRef.current;
            if (annotation?.editable && annotation.id) {
                const handler = getDrawingHandler(annotation.type);
                const renamed = {
                    ...(handler?.onRename?.({annotation, newName}) ??
                        annotation),
                    name: newName,
                };
                selectedAnnotationRef.current = renamed;
                annotationsControl.updateAnnotation(annotation.id, renamed);
            }
        },
        [annotationsControl]
    );

    // Space held: the pointer pans the image (the canvas lets it through)
    useEffect(() => {
        if (!active) {
            spaceRef.current = false;

            return;
        }
        const onSpaceDown = (e: KeyboardEvent) => {
            if (e.key === ' ' && !isTyping()) {
                spaceRef.current = true;
                e.preventDefault();
                canvasRef.current?.style.setProperty('pointer-events', 'none');
            }
        };
        const onSpaceUp = (e: KeyboardEvent) => {
            if (e.key === ' ') {
                spaceRef.current = false;
                canvasRef.current?.style.removeProperty('pointer-events');
            }
        };
        window.addEventListener('keydown', onSpaceDown);
        window.addEventListener('keyup', onSpaceUp);

        return () => {
            window.removeEventListener('keydown', onSpaceDown);
            window.removeEventListener('keyup', onSpaceUp);
        };
    }, [active]);

    // Delete removes the selected shape; Escape deselects it before the
    // viewer gets the key (a drawing in progress is handled by the tool)
    useEffect(() => {
        if (!active) {
            return;
        }
        const onKeyDown = (e: KeyboardEvent) => {
            if (isTyping()) {
                return;
            }
            if (e.key === 'Delete' || e.key === 'Backspace') {
                if (selectedAnnotationRef.current) {
                    e.preventDefault();
                    onShapeDelete();
                }
            } else if (e.key === 'Escape' && !mode) {
                if (selectedAnnotationRef.current) {
                    e.stopImmediatePropagation();
                    selectedAnnotationRef.current = undefined;
                    render();
                }
            }
        };
        window.addEventListener('keydown', onKeyDown, true);

        return () => window.removeEventListener('keydown', onKeyDown, true);
    }, [active, mode, onShapeDelete, render]);

    useAnnotationDraw({
        canvasRef,
        annotationsControl,
        selectedAnnotationRef,
        onTerminate: () => setMode(undefined),
        onSelect: () => forceRender(n => n + 1),
        mode,
        annotationOptions: options,
        setAnnotationOptions: setOptions,
        annotations,
        page,
        spaceRef,
        shapeControlRef,
        zoomRef,
        enabled: active,
    });

    if (!ctx || !target) {
        return {canvas: null, toolbar: null, active: false, target};
    }

    // The pointer is kept from the player (pan, zoom on double click)
    const stopWhenActive = (e: React.SyntheticEvent) => {
        if (active && !spaceRef.current) {
            e.stopPropagation();
        }
    };

    return {
        active,
        target,
        canvas: (
            <>
                <canvas
                    ref={canvasRef}
                    data-testid="annotation-layer"
                    data-active={active ? 'true' : undefined}
                    style={{
                        cursor: mode ? 'crosshair' : 'default',
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        zIndex: annotationZIndex + 1,
                        pointerEvents: active ? 'auto' : 'none',
                    }}
                    onPointerDown={stopWhenActive}
                    onClick={stopWhenActive}
                    onDoubleClick={stopWhenActive}
                />
                <ShapeControl
                    elementRef={shapeControlRef}
                    onDelete={onShapeDelete}
                    onDuplicate={onShapeDuplicate}
                    onRename={onRename}
                />
            </>
        ),
        toolbar: (
            <AnnotationToolbar
                canvasRef={canvasRef}
                annotationsControl={annotationsControl}
                selectedAnnotationRef={selectedAnnotationRef}
                annotate={annotate}
                setAnnotate={setAnnotate}
                options={options}
                setOptions={setOptions}
                mode={mode}
                setMode={setMode}
                label={target.label}
                onClose={() => ctx.clearTarget(target.key)}
                editable={editable}
            />
        ),
    };
}

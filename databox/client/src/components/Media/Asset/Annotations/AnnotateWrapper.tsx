import React, {
    forwardRef,
    memo,
    ReactNode,
    useCallback,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
} from 'react';
import {useAnnotationDraw} from './useAnnotationDraw.ts';
import {
    AnnotationOptions,
    AnnotationsControl,
    AnnotationType,
    AssetAnnotation,
    OnNewAnnotation,
} from './annotationTypes.ts';
import AnnotateToolbar from './AnnotateToolbar.tsx';
import {useAnnotationRender} from './useAnnotationRender.tsx';
import type {ZoomStepState} from "../Players";
import type {AssetAnnotationHandle, ZoomRef} from "./common.ts";
import {annotationZIndex} from "./common.ts";
import ShapeControl from "./ShapeControl.tsx";
import {drawingHandlers} from "./events.ts";

type Props = {
    annotationsControl?: AnnotationsControl | undefined;
    annotations: AssetAnnotation[] | undefined;
    page?: number;
    annotationEnabled?: boolean;
    children: (props: {
        canvas: ReactNode | null;
        toolbar: ReactNode | null;
        annotationActive: boolean;
        annotate: boolean;
    }) => JSX.Element;
    zoomStep: ZoomStepState;
    zoomRef: ZoomRef;
};

export default memo(
    forwardRef<AssetAnnotationHandle, Props>(function AnnotateWrapper(
        {
            annotationEnabled,
            annotationsControl,
            page,
            children,
            zoomStep,
            zoomRef,
            annotations: initialAnnotations,
        }: Props,
        ref
    ) {
        const selectedAnnotationRef = useRef<AssetAnnotation | undefined>();
        const shapeControlRef = useRef<HTMLDivElement | null>(null);
        const spaceRef = useRef<boolean>(false);
        const canvasRef = useRef<HTMLCanvasElement | null>(null);
        const [mode, setMode] = useState<AnnotationType | undefined>(undefined);
        const [annotate, setAnnotate] = useState(false);
        const [options, setOptions] = React.useState<AnnotationOptions>({
            color: '#000',
            size: 2,
        });
        const [annotations, setAnnotations] = React.useState<
            AssetAnnotation[] | undefined
        >(initialAnnotations);

        const onNewAnnotationHandler = React.useCallback<OnNewAnnotation>(
            annotation => {
                annotation.page = page;
                setAnnotations(p => (p ?? []).concat(annotation));
                annotationsControl?.onNew(annotation);
            },
            [annotationsControl]
        );

        React.useEffect(() => {
            setAnnotations(initialAnnotations);
        }, [annotate, initialAnnotations]);

        useEffect(() => {
            const onSpaceDown = (e: KeyboardEvent) => {
                if (e.key === ' ') {
                    spaceRef.current = true;
                    e.stopPropagation();
                    canvasRef.current?.style.setProperty('cursor', 'grab');
                }
            };
            const onSpaceUp = (e: KeyboardEvent) => {
                if (e.key === ' ') {
                    spaceRef.current = false;
                    e.stopPropagation();
                    canvasRef.current?.style.setProperty('cursor', 'default');
                }
            };
            window.addEventListener('keydown', onSpaceDown);
            window.addEventListener('keyup', onSpaceUp);

            return () => {
                window.removeEventListener('keydown', onSpaceDown);
                window.removeEventListener('keyup', onSpaceUp);
            }
        }, [spaceRef]);

        const onShapeDelete = useCallback(() => {
            const id = selectedAnnotationRef.current?.id;
            if (id) {
                setAnnotations(p => p!.filter(a => a.id !== id));
                annotationsControl?.onDelete(id);
                selectedAnnotationRef.current = undefined;
            }
        }, [annotationsControl, selectedAnnotationRef]);

        const onShapeDuplicate = useCallback(() => {
            const annotation = selectedAnnotationRef.current;
            if (annotation) {
                onNewAnnotationHandler({
                    ...annotation,
                    name: undefined,
                });
            }
        }, [annotationsControl, selectedAnnotationRef]);

        const onRename = useCallback((newName: string) => {
            const annotation = selectedAnnotationRef.current;
            if (annotation) {
                const handler = drawingHandlers[annotation.type];

                const newAnnotation = {
                    ...(handler?.onRename?.({annotation, newName}) ?? annotation),
                    name: newName,
                };

                setAnnotations(p => {
                    return p!.map(a => {
                        if (a.id === annotation.id) {
                            return newAnnotation;
                        }
                        return a;
                    });
                });
                annotationsControl?.onUpdate(annotation.id!, newAnnotation);
            }
        }, [annotationsControl, selectedAnnotationRef]);

        const sa = selectedAnnotationRef.current;
        if (sa) {
            if (annotations) {
                if (!annotations.includes(sa)) {
                    selectedAnnotationRef.current = annotations.find(a => a.id === sa.id);
                }
            } else {
                selectedAnnotationRef.current = undefined
            }
        }

        const {render} = useAnnotationRender({
            canvasRef,
            annotations,
            page,
            zoomStep,
            zoomRef,
            selectedAnnotationRef,
            shapeControlRef,
        });

        useImperativeHandle(ref, () => {
            return {
                render,
            };
        }, [render]);

        const resolvedAnnotationsControl: AnnotationsControl | undefined = annotationsControl
            ? {
                onNew: onNewAnnotationHandler,
                onUpdate: annotationsControl.onUpdate,
                onDelete: annotationsControl.onDelete,
            }
            : undefined;

        useAnnotationDraw({
            canvasRef,
            annotationsControl: resolvedAnnotationsControl,
            selectedAnnotationRef,
            onTerminate: () => setMode(undefined),
            mode,
            annotationOptions: options,
            setAnnotationOptions: setOptions,
            annotations,
            page,
            spaceRef,
            shapeControlRef,
            zoomRef,
        });

        return (
            <>
                {children({
                    canvas: (
                        <>
                            <canvas
                                ref={canvasRef}
                                style={{
                                    cursor:
                                        annotate && mode ? 'crosshair' : 'default',
                                    position: 'absolute',
                                    top: 0,
                                    left: 0,
                                    zIndex: annotationZIndex + 1,
                                    pointerEvents: annotate ? undefined : 'none',
                                }}
                            />
                            <ShapeControl
                                elementRef={shapeControlRef}
                                onDelete={onShapeDelete}
                                onDuplicate={onShapeDuplicate}
                                onRename={onRename}
                            />
                        </>
                    ),
                    toolbar:
                        annotationEnabled && annotationsControl ? (
                            <AnnotateToolbar
                                canvasRef={canvasRef}
                                annotationsControl={resolvedAnnotationsControl}
                                selectedAnnotationRef={selectedAnnotationRef}
                                annotate={annotate}
                                setAnnotate={setAnnotate}
                                options={options}
                                setOptions={setOptions}
                                mode={mode}
                                setMode={setMode}
                            />
                        ) : null,
                    annotationActive: !!mode,
                    annotate,
                })}
            </>
        );
    })
);

import {useTranslation} from 'react-i18next';
import React, {
    forwardRef,
    memo,
    ReactNode,
    useCallback,
    useEffect,
    useImperativeHandle, useMemo,
    useRef,
    useState,
} from 'react';
import {useAnnotationDraw} from './useAnnotationDraw.ts';
import {
    AnnotationOptions,
    AnnotationsControl,
    AnnotationType,
    AssetAnnotation, OnDeleteAnnotation,
    OnNewAnnotation, OnUpdateAnnotation,
} from './annotationTypes.ts';
import AnnotateToolbar from './AnnotateToolbar.tsx';
import {useAnnotationRender} from './useAnnotationRender.tsx';
import type {ZoomStepState} from '../Players';
import type {ZoomRef} from './common.ts';
import {annotationZIndex} from './common.ts';
import ShapeControl from './ShapeControl.tsx';
import {drawingHandlers} from './events.ts';

export type BaseAnnotationProps = {
    annotations?: AssetAnnotation[] | undefined;
    onNewAnnotation?: OnNewAnnotation | undefined;
    onUpdateAnnotation?: OnUpdateAnnotation | undefined;
    onDeleteAnnotation?: OnDeleteAnnotation | undefined;
}

type Props = {
    page?: number;
    annotationEnabled?: boolean;
    children: (props: {
        canvas: ReactNode;
        toolbar: ReactNode | null;
        annotationActive: boolean;
        annotate: boolean;
    }) => JSX.Element;
    zoomStep: ZoomStepState;
    zoomRef: ZoomRef;
} & BaseAnnotationProps;

let annotationIncrement = 1;

export default memo(
    forwardRef<AnnotationsControl, Props>(function AnnotateWrapper(
        {
            annotationEnabled,
            page,
            children,
            zoomStep,
            zoomRef,
            annotations: initialAnnotations,
            onNewAnnotation,
            onUpdateAnnotation,
            onDeleteAnnotation,
        }: Props,
        ref
    ) {
        const {t} = useTranslation();
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
            AssetAnnotation[]
        >(initialAnnotations ?? []);

        React.useEffect(() => {
            const onKeyDown = (e: KeyboardEvent) => {
                if (e.key === 'Escape') {
                    e.stopPropagation();
                    e.preventDefault();
                    setMode(undefined);
                    setAnnotate(false);
                } else if (e.key === 'Delete') {
                    onShapeDelete();
                }
            }
            document.addEventListener('keydown', onKeyDown);
            window.addEventListener('keydown', onKeyDown);

            return () => {
                document.removeEventListener('keydown', onKeyDown);
                window.removeEventListener('keydown', onKeyDown);
            }
        }, []);

        const onNewAnnotationHandler = React.useCallback<OnNewAnnotation>(
            annotation => {
                annotation.id = `annotation-${(annotationIncrement++).toString()}`;
                annotation.editable = true;

                const annotationTypes: Record<AnnotationType, string> = {
                    [AnnotationType.Draw]: t(
                        'annotation.type.draw',
                        'Draw'
                    ),
                    [AnnotationType.Line]: t(
                        'annotation.type.line',
                        'Line'
                    ),
                    [AnnotationType.Arrow]: t(
                        'annotation.type.arrow',
                        'Arrow'
                    ),
                    [AnnotationType.Text]: t(
                        'annotation.type.text',
                        'Text'
                    ),
                    [AnnotationType.Cue]: t('annotation.type.cue', 'Cue'),
                    [AnnotationType.Circle]: t(
                        'annotation.type.circle',
                        'Circle'
                    ),
                    [AnnotationType.Rect]: t(
                        'annotation.type.rectangle',
                        'Rectangle'
                    ),
                    [AnnotationType.Target]: t(
                        'annotation.type.target',
                        'Target'
                    ),
                    [AnnotationType.TimeRange]: t(
                        'annotation.type.timerange',
                        'Time Range'
                    ),
                };

                annotation.page = page;
                annotation.name =
                    annotation.name ??
                    t('form.annotation.default_name', {
                        defaultValue: '{{type}} #{{n}}',
                        type: annotationTypes[annotation.type],
                        n:
                            annotations.filter(
                                a =>
                                    a.type === annotation.type &&
                                    a.page === annotation.page
                            ).length + 1,
                    });
                setAnnotations(p => (p ?? []).concat(annotation));

                onNewAnnotation?.(annotation);
            },
            [t, onNewAnnotation, annotations]
        );

        React.useEffect(() => {
            setAnnotations(initialAnnotations ?? []);
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
            };
        }, [spaceRef]);

        const sa = selectedAnnotationRef.current;
        if (sa) {
            if (annotations.length > 0) {
                if (!annotations.includes(sa)) {
                    selectedAnnotationRef.current = annotations.find(
                        a => a.id === sa.id
                    );
                }
            } else {
                selectedAnnotationRef.current = undefined;
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

        const annotationsControl = useMemo<AnnotationsControl>(() => {
            return {
                render,
                addAnnotation: onNewAnnotationHandler,
                updateAnnotation: (id, newAnnotation) => {
                    if (!newAnnotation.editable) {
                        return newAnnotation;
                    }

                    setAnnotations(p => {
                        return p!.map(a => {
                            if (a.id === id) {
                                return newAnnotation;
                            }
                            return a;
                        });
                    });

                    onUpdateAnnotation?.(id, newAnnotation);

                    return newAnnotation;
                },
                deleteAnnotation: id => {
                    setAnnotations(p => p!.filter(a => a.id !== id));
                    onDeleteAnnotation?.(id);
                },
                selectAnnotation: annotation => {
                    selectedAnnotationRef.current = annotation;
                },
                replaceAnnotations: annotations => {
                    setAnnotations(annotations);
                },
            };
        }, [
            render,
            onNewAnnotationHandler,
            onUpdateAnnotation,
            onDeleteAnnotation,
            setAnnotations,
            selectedAnnotationRef,

        ]);

        useImperativeHandle(ref, () => annotationsControl, [annotationsControl]);

        const onShapeDelete = useCallback(() => {
            const selected = selectedAnnotationRef.current;
            if (selected?.editable && selected!.id) {
                const id = selected.id;
                setAnnotations(p => p!.filter(a => a.id !== id));
                annotationsControl.deleteAnnotation(id);
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
        }, [selectedAnnotationRef]);

        const onRename = useCallback(
            (newName: string) => {
                const annotation = selectedAnnotationRef.current;
                if (annotation && annotation.editable) {
                    const handler = drawingHandlers[annotation.type];

                    const newAnnotation = {
                        ...(handler?.onRename?.({annotation, newName}) ??
                            annotation),
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

                    annotationsControl.updateAnnotation(annotation.id!, newAnnotation);
                }
            },
            [annotationsControl, selectedAnnotationRef]
        );

        useAnnotationDraw({
            canvasRef,
            annotationsControl,
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
                                        annotate && mode
                                            ? 'crosshair'
                                            : 'default',
                                    position: 'absolute',
                                    top: 0,
                                    left: 0,
                                    zIndex: annotationZIndex + 1,
                                    pointerEvents: annotate
                                        ? undefined
                                        : 'none',
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
                        annotationEnabled ? (
                            <AnnotateToolbar
                                canvasRef={canvasRef}
                                annotationsControl={annotationsControl}
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

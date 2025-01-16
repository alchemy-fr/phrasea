import React, {
    forwardRef,
    memo,
    ReactNode,
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
};

export const annotationZIndex = 100;

export type AssetAnnotationHandle = {
    render: () => void;
};

export default memo(
    forwardRef<AssetAnnotationHandle, Props>(function AnnotateWrapper(
        {
            annotationEnabled,
            annotationsControl,
            page,
            children,
            annotations: initialAnnotations,
        }: Props,
        ref
    ) {
        const selectedAnnotationRef = useRef<AssetAnnotation | undefined>();
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

        const {render} = useAnnotationRender({
            canvasRef,
            annotations,
            page,
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
        });

        return (
            <>
                {children({
                    canvas: (
                        <canvas
                            ref={canvasRef}
                            style={{
                                cursor:
                                    annotate && mode ? 'crosshair' : undefined,
                                position: 'absolute',
                                top: 0,
                                left: 0,
                                zIndex: annotationZIndex + 1,
                                pointerEvents: annotate ? undefined : 'none',
                            }}
                        />
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

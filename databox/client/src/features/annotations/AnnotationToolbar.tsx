'use client';

import {ReactNode, useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {
    BrushIcon,
    CircleIcon,
    CrosshairIcon,
    LucideIcon,
    MoveUpRightIcon,
    ShapesIcon,
    SlashIcon,
    SquareIcon,
    TypeIcon,
    XIcon,
} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {ColorInput} from '@/components/ui/color-input';
import {Input} from '@/components/ui/input';
import {cn} from '@/lib/utils/cn';
import {
    AnnotationOptions,
    AnnotationsControl,
    AnnotationType,
    CanvasRef,
    SelectedAnnotationRef,
    StateSetter,
} from './types';
import {getDrawingHandler} from './handlers';
import {getDefaultOptions, updateLastOptions} from './defaultOptions';

/** The options apply to the selected shape (when editable) */
function changeIfSelected(
    canvasRef: CanvasRef,
    annotationsControl: AnnotationsControl | undefined,
    selectedAnnotationRef: SelectedAnnotationRef,
    options: AnnotationOptions
): AnnotationOptions {
    const annotation = selectedAnnotationRef.current;
    if (annotation) {
        updateLastOptions(annotation.type as AnnotationType, options);
    }

    if (annotationsControl && annotation?.editable && canvasRef.current) {
        const handler = getDrawingHandler(annotation.type);
        if (handler) {
            const canvas = canvasRef.current;
            // After the state update that carries the new options
            setTimeout(() => {
                const newAnnotation = handler.fromOptions(options, annotation, {
                    relativeX: x => x / canvas.offsetWidth,
                    relativeY: y => y / canvas.offsetHeight,
                });
                annotationsControl.updateAnnotation(
                    annotation.id!,
                    newAnnotation
                );
                selectedAnnotationRef.current = newAnnotation;
            }, 0);
        }
    }

    return options;
}

type Props = {
    mode: AnnotationType | undefined;
    setMode: StateSetter<AnnotationType | undefined>;
    options: AnnotationOptions;
    setOptions: StateSetter<AnnotationOptions>;
    annotate: boolean;
    setAnnotate: StateSetter<boolean>;
    annotationsControl: AnnotationsControl | undefined;
    selectedAnnotationRef: SelectedAnnotationRef;
    canvasRef: CanvasRef;
    /** What is annotated */
    label?: string;
    /** Shapes may be drawn: the tools are shown */
    editable?: boolean;
    /** Stops showing the annotations */
    onClose?: () => void;
};

export function AnnotationToolbar({
    mode,
    setMode,
    options,
    setOptions,
    annotate,
    setAnnotate,
    annotationsControl,
    selectedAnnotationRef,
    canvasRef,
    label,
    editable,
    onClose,
}: Props) {
    const {t} = useTranslation();
    // Options changed before picking a tool apply to it
    const changedOptions = useRef<Partial<AnnotationOptions>>({});

    useEffect(() => {
        if (mode) {
            const co = {...changedOptions.current};
            changedOptions.current = {};

            setOptions(p => ({
                ...p,
                ...getDefaultOptions(mode),
                ...co,
            }));
        }
    }, [mode, setOptions]);

    const tools: {type: AnnotationType; icon: LucideIcon; label: string}[] = [
        {
            type: AnnotationType.Text,
            icon: TypeIcon,
            label: t('annotation.type.text', 'Text'),
        },
        {
            type: AnnotationType.Target,
            icon: CrosshairIcon,
            label: t('annotation.type.target', 'Target'),
        },
        {
            type: AnnotationType.Rect,
            icon: SquareIcon,
            label: t('annotation.type.rectangle', 'Rectangle'),
        },
        {
            type: AnnotationType.Circle,
            icon: CircleIcon,
            label: t('annotation.type.circle', 'Circle'),
        },
        {
            type: AnnotationType.Arrow,
            icon: MoveUpRightIcon,
            label: t('annotation.type.arrow', 'Arrow'),
        },
        {
            type: AnnotationType.Line,
            icon: SlashIcon,
            label: t('annotation.type.line', 'Line'),
        },
        {
            type: AnnotationType.Draw,
            icon: BrushIcon,
            label: t('annotation.type.draw', 'Drawing'),
        },
    ];

    const button = (
        label: string,
        icon: ReactNode,
        onClick: () => void,
        pressed?: boolean,
        className?: string
    ) => (
        <Tooltip content={label}>
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label={label}
                aria-pressed={pressed}
                className={cn(
                    pressed && 'bg-accent text-accent-foreground',
                    className
                )}
                onClick={onClick}
            >
                {icon}
            </Button>
        </Tooltip>
    );

    return (
        <div
            data-annotation-toolbar
            className="flex items-center gap-1"
            role="toolbar"
            aria-label={t('annotation.toolbar', 'Annotations')}
        >
            {label ? (
                <span
                    className="max-w-40 truncate px-1 text-xs text-muted-foreground"
                    title={label}
                >
                    {label}
                </span>
            ) : null}
            {editable
                ? button(
                      annotate
                          ? t('annotation.stop', 'Stop annotating')
                          : t('annotation.start', 'Annotate'),
                      <ShapesIcon />,
                      () => {
                          setMode(undefined);
                          setAnnotate(p => !p);
                      },
                      annotate
                  )
                : null}
            {editable && annotate ? (
                <>
                    {tools.map(tool =>
                        button(
                            tool.label,
                            <tool.icon />,
                            () =>
                                setMode(m =>
                                    m === tool.type ? undefined : tool.type
                                ),
                            mode === tool.type
                        )
                    )}
                    <ColorInput
                        value={options.color}
                        withInput={false}
                        clearable={false}
                        aria-label={t('annotation.color', 'Color')}
                        swatchClassName="size-5"
                        onChange={c => {
                            if (!c) {
                                return;
                            }
                            setOptions(p => {
                                if (!selectedAnnotationRef.current) {
                                    changedOptions.current.color = c;
                                }

                                return changeIfSelected(
                                    canvasRef,
                                    annotationsControl,
                                    selectedAnnotationRef,
                                    {...p, color: c}
                                );
                            });
                        }}
                    />
                    <Tooltip content={t('annotation.size', 'Size')}>
                        <Input
                            type="number"
                            aria-label={t('annotation.size', 'Size')}
                            min={0.1}
                            step={options.size <= 1 ? 0.1 : 1}
                            className="h-6 w-16 px-1 text-xs"
                            value={Math.round(options.size * 10) / 10}
                            onKeyDown={e => e.stopPropagation()}
                            onChange={e => {
                                const size = Math.max(
                                    0.1,
                                    parseFloat(e.target.value) || 1
                                );
                                if (!selectedAnnotationRef.current) {
                                    changedOptions.current.size = size;
                                }
                                setOptions(p =>
                                    changeIfSelected(
                                        canvasRef,
                                        annotationsControl,
                                        selectedAnnotationRef,
                                        {...p, size}
                                    )
                                );
                            }}
                        />
                    </Tooltip>
                </>
            ) : null}
            {onClose
                ? button(
                      t('annotation.hide', 'Hide annotations'),
                      <XIcon />,
                      onClose
                  )
                : null}
            <span className="mx-0.5 h-4 w-px bg-border" />
        </div>
    );
}

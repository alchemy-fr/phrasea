'use client';

import {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {
    BrushIcon,
    CircleIcon,
    CrosshairIcon,
    LucideIcon,
    MousePointer2Icon,
    MoveUpRightIcon,
    PaintBucketIcon,
    ScanFaceIcon,
    ShapesIcon,
    SlashIcon,
    SquareIcon,
    TypeIcon,
    XIcon,
} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
    Tooltip,
} from '@/components/ui/overlays';
import {ColorInput} from '@/components/ui/color-input';
import {Input} from '@/components/ui/input';
import {Slider} from '@/components/ui/controls';
import {cn} from '@/lib/utils/cn';
import {AnnotationType} from './types';
import {getShapeModel} from './shapes';
import type {AnnotationEditor} from './useAnnotationEditor';

type Props = {
    editor: AnnotationEditor;
    /** What is annotated */
    label?: string;
    /** Stops showing the annotations */
    onClose?: () => void;
};

/**
 * The tools of the layer, in the toolbar of the player: the selection and
 * drawing tools, the color, fill and size of the tool or of the selected
 * shape, pausing the tools and hiding the annotations.
 */
export function AnnotationToolbar({editor, label, onClose}: Props) {
    const {t} = useTranslation();
    const {tool, setTool, annotate, setAnnotate, options, setOptions} = editor;
    const {editable} = editor;

    const tools: {type: AnnotationType; icon: LucideIcon; label: string}[] = [
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
            type: AnnotationType.Face,
            icon: ScanFaceIcon,
            label: t('annotation.type.face', 'Face'),
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
            type: AnnotationType.Target,
            icon: CrosshairIcon,
            label: t('annotation.type.target', 'Target'),
        },
        {
            type: AnnotationType.Text,
            icon: TypeIcon,
            label: t('annotation.type.text', 'Text'),
        },
        {
            type: AnnotationType.Draw,
            icon: BrushIcon,
            label: t('annotation.type.draw', 'Drawing'),
        },
    ];

    const button = (
        key: string,
        label: string,
        icon: ReactNode,
        onClick: () => void,
        pressed?: boolean,
        className?: string
    ) => (
        <Tooltip key={key} content={label}>
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                aria-label={label}
                aria-pressed={pressed}
                data-tool={key}
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

    // The options shown are the ones of the selected shape, else of the tool
    const optionsType = editor.selected?.type ?? tool;
    const optionsModel = getShapeModel(optionsType);
    const fillable = !!optionsModel?.fillable;
    // A shape drawn by the client has no style option
    const styled = !optionsModel?.fixedStyle;
    const isText = optionsType === AnnotationType.Text;
    const maxSize = isText ? 120 : 40;

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
                      'annotate',
                      annotate
                          ? t('annotation.stop', 'Stop annotating')
                          : t('annotation.start', 'Annotate'),
                      <ShapesIcon />,
                      () => setAnnotate(!annotate),
                      annotate
                  )
                : null}
            {editable && annotate ? (
                <>
                    {button(
                        'select',
                        t('annotation.select', 'Select'),
                        <MousePointer2Icon />,
                        () => setTool(undefined),
                        !tool
                    )}
                    {tools.map(item =>
                        button(
                            item.type,
                            item.label,
                            <item.icon />,
                            () =>
                                setTool(
                                    tool === item.type ? undefined : item.type
                                ),
                            tool === item.type
                        )
                    )}
                </>
            ) : null}
            {editable && annotate && styled ? (
                <>
                    <span className="mx-0.5 h-4 w-px bg-border" />
                    <ColorInput
                        value={options.color}
                        withInput={false}
                        clearable={false}
                        aria-label={t('annotation.color', 'Color')}
                        swatchClassName="size-5"
                        onChange={c => {
                            if (c) {
                                setOptions({...options, color: c});
                            }
                        }}
                    />
                    {fillable ? (
                        <span
                            className="relative inline-flex"
                            title={t('annotation.fill', 'Fill')}
                        >
                            <ColorInput
                                value={options.fillColor}
                                withInput={false}
                                aria-label={t('annotation.fill', 'Fill')}
                                swatchClassName="size-5"
                                onChange={c =>
                                    setOptions({
                                        ...options,
                                        fillColor: c || undefined,
                                    })
                                }
                            />
                            <PaintBucketIcon className="pointer-events-none absolute -right-1 -bottom-1 size-3 rounded-full bg-background p-px text-muted-foreground" />
                        </span>
                    ) : null}
                    <Popover>
                        <Tooltip
                            content={
                                isText
                                    ? t('annotation.font_size', 'Font size')
                                    : t('annotation.size', 'Size')
                            }
                        >
                            <PopoverTrigger asChild>
                                <button
                                    type="button"
                                    data-testid="annotation-size"
                                    aria-label={t('annotation.size', 'Size')}
                                    className="flex h-6 min-w-10 items-center justify-center gap-1 rounded-md px-1.5 font-mono text-xs hover:bg-accent"
                                >
                                    <span
                                        className="inline-block rounded-full bg-foreground"
                                        style={{
                                            width: Math.min(
                                                12,
                                                Math.max(3, options.size / 2)
                                            ),
                                            height: Math.min(
                                                12,
                                                Math.max(3, options.size / 2)
                                            ),
                                        }}
                                    />
                                    {Math.round(options.size)}
                                </button>
                            </PopoverTrigger>
                        </Tooltip>
                        <PopoverContent
                            align="center"
                            className="flex w-56 items-center gap-3 p-3"
                        >
                            <Slider
                                min={1}
                                max={maxSize}
                                step={1}
                                value={[
                                    Math.min(
                                        maxSize,
                                        Math.max(1, options.size)
                                    ),
                                ]}
                                aria-label={t('annotation.size', 'Size')}
                                onValueChange={([size]) =>
                                    setOptions({...options, size})
                                }
                            />
                            <Input
                                type="number"
                                min={1}
                                step={1}
                                className="h-7 w-16 px-1 text-xs"
                                value={Math.round(options.size)}
                                onKeyDown={e => e.stopPropagation()}
                                onChange={e => {
                                    const size = parseFloat(e.target.value);
                                    if (size >= 1) {
                                        setOptions({...options, size});
                                    }
                                }}
                            />
                        </PopoverContent>
                    </Popover>
                </>
            ) : null}
            {onClose
                ? button(
                      'close',
                      t('annotation.hide', 'Hide annotations'),
                      <XIcon />,
                      onClose
                  )
                : null}
            <span className="mx-0.5 h-4 w-px bg-border" />
        </div>
    );
}

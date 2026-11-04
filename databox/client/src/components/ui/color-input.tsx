'use client';

import {CSSProperties, ReactNode, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Popover, PopoverContent, PopoverTrigger} from './overlays';
import {Input} from './input';
import {Button} from './button';
import {cn} from '@/lib/utils/cn';

const HEX_RE = /^#[0-9a-f]{6}$/i;

const PRESETS = [
    '#000000',
    '#6b7280',
    '#ffffff',
    '#ef4444',
    '#f97316',
    '#f59e0b',
    '#eab308',
    '#84cc16',
    '#22c55e',
    '#14b8a6',
    '#06b6d4',
    '#3b82f6',
    '#6366f1',
    '#8b5cf6',
    '#d946ef',
    '#ec4899',
];

export function isValidHex(value: string | undefined | null): value is string {
    return !!value && HEX_RE.test(value);
}

type Hsv = {h: number; s: number; v: number};

export function hexToHsv(hex: string): Hsv {
    const n = parseInt(hex.slice(1), 16);
    const r = ((n >> 16) & 255) / 255;
    const g = ((n >> 8) & 255) / 255;
    const b = (n & 255) / 255;
    const max = Math.max(r, g, b);
    const d = max - Math.min(r, g, b);
    let h = 0;
    if (d) {
        if (max === r) {
            h = ((g - b) / d) % 6;
        } else if (max === g) {
            h = (b - r) / d + 2;
        } else {
            h = (r - g) / d + 4;
        }
        h = (h * 60 + 360) % 360;
    }

    return {h, s: max ? d / max : 0, v: max};
}

export function hsvToHex({h, s, v}: Hsv): string {
    const f = (n: number) => {
        const k = (n + h / 60) % 6;
        const c = v - v * s * Math.max(0, Math.min(k, 4 - k, 1));

        return Math.round(c * 255)
            .toString(16)
            .padStart(2, '0');
    };

    return `#${f(5)}${f(3)}${f(1)}`;
}

/** Color preview; an empty value is shown as white with a diagonal slash. */
export function ColorSwatch({
    color,
    className,
    ...props
}: {color: string | undefined} & React.ComponentProps<'span'>) {
    const valid = isValidHex(color);

    return (
        <span
            className={cn(
                'inline-block size-9 shrink-0 rounded border',
                className
            )}
            style={
                valid
                    ? {backgroundColor: color}
                    : {
                          backgroundColor: '#fff',
                          backgroundImage:
                              'linear-gradient(to top right, transparent calc(50% - 1px), #ef4444 calc(50% - 1px), #ef4444 calc(50% + 1px), transparent calc(50% + 1px))',
                      }
            }
            {...props}
        />
    );
}

type Props = {
    'value': string | undefined;
    'onChange': (value: string) => void;
    'id'?: string;
    'disabled'?: boolean;
    'readOnly'?: boolean;
    /** Show the "Clear" action (default: true) */
    'clearable'?: boolean;
    /** Show the hex text field next to the swatch (default: true) */
    'withInput'?: boolean;
    'swatchClassName'?: string;
    'className'?: string;
    'aria-label'?: string;
};

/**
 * Color field: a swatch opening a picker (saturation / hue, hex code,
 * presets) and, optionally, the hex code as a text field.
 */
export function ColorInput({
    value,
    onChange,
    id,
    disabled,
    readOnly,
    clearable = true,
    withInput = true,
    swatchClassName,
    className,
    'aria-label': ariaLabel,
}: Props) {
    const {t} = useTranslation();
    const locked = disabled || readOnly;

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <Popover>
                <PopoverTrigger asChild disabled={locked}>
                    <button
                        type="button"
                        id={withInput ? undefined : id}
                        aria-label={
                            ariaLabel ?? t('color.pick', 'Pick a color')
                        }
                        className={cn(
                            'shrink-0 rounded outline-none focus-visible:ring-2 focus-visible:ring-ring/60',
                            locked
                                ? 'cursor-not-allowed opacity-50'
                                : 'cursor-pointer'
                        )}
                    >
                        <ColorSwatch
                            color={value}
                            className={swatchClassName}
                        />
                    </button>
                </PopoverTrigger>
                <PopoverContent align="start" className="w-64 p-3">
                    <ColorPickerPanel
                        value={value}
                        onChange={onChange}
                        clearable={clearable}
                    />
                </PopoverContent>
            </Popover>
            {withInput ? (
                <Input
                    id={id}
                    value={value ?? ''}
                    onChange={e => onChange(e.target.value)}
                    placeholder="#rrggbb"
                    className="w-32 font-mono"
                    disabled={disabled}
                    readOnly={readOnly}
                    aria-invalid={!!value && !isValidHex(value)}
                />
            ) : null}
        </div>
    );
}

function ColorPickerPanel({
    value,
    onChange,
    clearable,
}: {
    value: string | undefined;
    onChange: (value: string) => void;
    clearable: boolean;
}) {
    const {t} = useTranslation();
    // Kept locally: the hue must survive a grey (s = 0) or black (v = 0) color
    const [hsv, setHsv] = useState<Hsv>(() =>
        hexToHsv(isValidHex(value) ? value : '#888888')
    );
    const [text, setText] = useState(value ?? '');

    const apply = (next: Hsv) => {
        setHsv(next);
        const hex = hsvToHex(next);
        setText(hex);
        onChange(hex);
    };
    const applyHex = (hex: string) => {
        setText(hex);
        if (isValidHex(hex)) {
            setHsv(hexToHsv(hex));
            onChange(hex.toLowerCase());
        }
    };

    return (
        <div className="space-y-3">
            <DragArea
                className="h-36 cursor-crosshair rounded"
                style={{backgroundColor: `hsl(${hsv.h} 100% 50%)`}}
                onDrag={(x, y) => apply({...hsv, s: x, v: 1 - y})}
            >
                <div className="absolute inset-0 rounded bg-linear-to-r from-white to-transparent" />
                <div className="absolute inset-0 rounded bg-linear-to-t from-black to-transparent" />
                <Thumb
                    style={{
                        left: `${hsv.s * 100}%`,
                        top: `${(1 - hsv.v) * 100}%`,
                    }}
                />
            </DragArea>
            <DragArea
                className="h-3 cursor-pointer rounded-full"
                style={{
                    background:
                        'linear-gradient(to right, #f00, #ff0, #0f0, #0ff, #00f, #f0f, #f00)',
                }}
                onDrag={x => apply({...hsv, h: Math.min(x * 360, 359.9)})}
            >
                <Thumb style={{left: `${(hsv.h / 360) * 100}%`, top: '50%'}} />
            </DragArea>
            <div className="flex items-center gap-2">
                <ColorSwatch color={text} className="size-8" />
                <Input
                    value={text}
                    onChange={e => applyHex(e.target.value.trim())}
                    placeholder="#rrggbb"
                    spellCheck={false}
                    aria-label={t('color.hex', 'Hex code')}
                    aria-invalid={!!text && !isValidHex(text)}
                    className="h-8 flex-1 font-mono"
                />
            </div>
            <div className="grid grid-cols-8 gap-1.5">
                {PRESETS.map(c => (
                    <button
                        key={c}
                        type="button"
                        title={c}
                        onClick={() => applyHex(c)}
                        className={cn(
                            'aspect-square rounded border outline-none focus-visible:ring-2 focus-visible:ring-ring/60',
                            text.toLowerCase() === c &&
                                'ring-2 ring-primary ring-offset-1'
                        )}
                        style={{backgroundColor: c}}
                    />
                ))}
            </div>
            {clearable ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="w-full"
                    disabled={!value}
                    onClick={() => {
                        setText('');
                        onChange('');
                    }}
                >
                    <ColorSwatch color={undefined} className="size-4" />
                    {t('color.none', 'No color')}
                </Button>
            ) : null}
        </div>
    );
}

function DragArea({
    className,
    style,
    onDrag,
    children,
}: {
    className?: string;
    style?: CSSProperties;
    onDrag: (x: number, y: number) => void;
    children: ReactNode;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const update = (e: React.PointerEvent) => {
        const r = ref.current!.getBoundingClientRect();
        const clamp = (n: number) => Math.min(1, Math.max(0, n));
        onDrag(
            clamp((e.clientX - r.left) / r.width),
            clamp((e.clientY - r.top) / r.height)
        );
    };

    return (
        <div
            ref={ref}
            className={cn('relative touch-none select-none', className)}
            style={style}
            onPointerDown={e => {
                e.currentTarget.setPointerCapture(e.pointerId);
                update(e);
            }}
            onPointerMove={e => {
                if (e.currentTarget.hasPointerCapture(e.pointerId)) {
                    update(e);
                }
            }}
        >
            {children}
        </div>
    );
}

function Thumb({style}: {style: CSSProperties}) {
    return (
        <span
            className="pointer-events-none absolute size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgb(0_0_0/0.4)]"
            style={style}
        />
    );
}

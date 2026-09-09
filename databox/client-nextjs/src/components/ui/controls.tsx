'use client';

import * as React from 'react';
import {
    Checkbox as CheckboxPrimitive,
    Switch as SwitchPrimitive,
    Slider as SliderPrimitive,
    RadioGroup as RadioPrimitive,
    Toggle as TogglePrimitive,
} from 'radix-ui';
import {CheckIcon, MinusIcon} from 'lucide-react';
import {cn} from '@/lib/utils/cn';

export function Checkbox({
    className,
    ...props
}: React.ComponentProps<typeof CheckboxPrimitive.Root>) {
    return (
        <CheckboxPrimitive.Root
            data-slot="checkbox"
            className={cn(
                'peer flex size-4 shrink-0 items-center justify-center rounded-[4px] border border-input shadow-xs outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/60 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground',
                className
            )}
            {...props}
        >
            <CheckboxPrimitive.Indicator className="flex items-center justify-center text-current">
                {props.checked === 'indeterminate' ? (
                    <MinusIcon className="size-3.5" />
                ) : (
                    <CheckIcon className="size-3.5" />
                )}
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}

export function Switch({
    className,
    ...props
}: React.ComponentProps<typeof SwitchPrimitive.Root>) {
    return (
        <SwitchPrimitive.Root
            data-slot="switch"
            className={cn(
                'peer inline-flex h-5 w-9 shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all outline-none focus-visible:ring-2 focus-visible:ring-ring/60 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
                className
            )}
            {...props}
        >
            <SwitchPrimitive.Thumb className="pointer-events-none block size-4 rounded-full bg-background shadow-sm ring-0 transition-transform data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0.5" />
        </SwitchPrimitive.Root>
    );
}

export function Slider({
    className,
    defaultValue,
    value,
    min = 0,
    max = 100,
    ...props
}: React.ComponentProps<typeof SliderPrimitive.Root>) {
    const values = React.useMemo(
        () =>
            Array.isArray(value)
                ? value
                : Array.isArray(defaultValue)
                  ? defaultValue
                  : [min, max],
        [value, defaultValue, min, max]
    );

    return (
        <SliderPrimitive.Root
            data-slot="slider"
            defaultValue={defaultValue}
            value={value}
            min={min}
            max={max}
            className={cn(
                'relative flex w-full touch-none items-center select-none data-[disabled]:opacity-50',
                className
            )}
            {...props}
        >
            <SliderPrimitive.Track className="relative h-1.5 w-full grow overflow-hidden rounded-full bg-muted">
                <SliderPrimitive.Range className="absolute h-full bg-primary" />
            </SliderPrimitive.Track>
            {Array.from({length: values.length}, (_, i) => (
                <SliderPrimitive.Thumb
                    key={i}
                    className="block size-4 shrink-0 rounded-full border border-primary bg-background shadow-sm transition-colors hover:ring-4 hover:ring-ring/30 focus-visible:ring-4 focus-visible:ring-ring/30 focus-visible:outline-hidden"
                />
            ))}
        </SliderPrimitive.Root>
    );
}

export const RadioGroup = RadioPrimitive.Root;

export function RadioGroupItem({
    className,
    ...props
}: React.ComponentProps<typeof RadioPrimitive.Item>) {
    return (
        <RadioPrimitive.Item
            className={cn(
                'aspect-square size-4 shrink-0 rounded-full border border-input shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/60 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-primary',
                className
            )}
            {...props}
        >
            <RadioPrimitive.Indicator className="relative flex items-center justify-center">
                <span className="absolute size-2 rounded-full bg-primary" />
            </RadioPrimitive.Indicator>
        </RadioPrimitive.Item>
    );
}

export function Toggle({
    className,
    size = 'default',
    ...props
}: React.ComponentProps<typeof TogglePrimitive.Root> & {
    size?: 'default' | 'sm';
}) {
    return (
        <TogglePrimitive.Root
            className={cn(
                'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium transition-colors hover:bg-muted hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 data-[state=on]:bg-accent data-[state=on]:text-accent-foreground [&_svg]:size-4',
                size === 'default' ? 'h-9 px-2 min-w-9' : 'h-8 px-1.5 min-w-8',
                className
            )}
            {...props}
        />
    );
}

export function LabeledControl({
    label,
    children,
    className,
    description,
}: {
    label: React.ReactNode;
    children: React.ReactNode;
    className?: string;
    description?: React.ReactNode;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-start gap-2.5 text-sm select-none',
                className
            )}
        >
            <span className="mt-0.5">{children}</span>
            <span className="flex flex-col">
                <span>{label}</span>
                {description ? (
                    <span className="text-xs text-muted-foreground">
                        {description}
                    </span>
                ) : null}
            </span>
        </label>
    );
}

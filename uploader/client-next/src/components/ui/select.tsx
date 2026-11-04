'use client';

import * as React from 'react';
import {Select as SelectPrimitive} from 'radix-ui';
import {useTranslation} from 'react-i18next';
import {CheckIcon, ChevronDownIcon, ChevronUpIcon} from 'lucide-react';
import {cn} from '@/lib/utils/cn';
import {Popover, PopoverContent, PopoverTrigger} from './overlays';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from './command';

export const Select = SelectPrimitive.Root;
export const SelectGroup = SelectPrimitive.Group;
export const SelectValue = SelectPrimitive.Value;

export function SelectTrigger({
    className,
    size = 'default',
    children,
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Trigger> & {
    size?: 'sm' | 'default';
}) {
    return (
        <SelectPrimitive.Trigger
            data-size={size}
            className={cn(
                "flex w-fit items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-xs outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/60 disabled:cursor-not-allowed disabled:opacity-50 data-[placeholder]:text-muted-foreground data-[size=default]:h-9 data-[size=sm]:h-8 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
                className
            )}
            {...props}
        >
            {children}
            <SelectPrimitive.Icon asChild>
                <ChevronDownIcon className="size-4 opacity-50" />
            </SelectPrimitive.Icon>
        </SelectPrimitive.Trigger>
    );
}

export function SelectContent({
    className,
    children,
    position = 'popper',
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Content>) {
    return (
        <SelectPrimitive.Portal>
            <SelectPrimitive.Content
                className={cn(
                    'relative z-[70] max-h-(--radix-select-content-available-height) min-w-[8rem] overflow-x-hidden overflow-y-auto rounded-md border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fill-mode-forwards data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                    position === 'popper' &&
                        'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
                    className
                )}
                position={position}
                {...props}
            >
                <SelectPrimitive.ScrollUpButton className="flex cursor-default items-center justify-center py-1">
                    <ChevronUpIcon className="size-4" />
                </SelectPrimitive.ScrollUpButton>
                <SelectPrimitive.Viewport
                    className={cn(
                        'p-1',
                        position === 'popper' &&
                            'h-[var(--radix-select-trigger-height)] w-full min-w-[var(--radix-select-trigger-width)] scroll-my-1'
                    )}
                >
                    {children}
                </SelectPrimitive.Viewport>
                <SelectPrimitive.ScrollDownButton className="flex cursor-default items-center justify-center py-1">
                    <ChevronDownIcon className="size-4" />
                </SelectPrimitive.ScrollDownButton>
            </SelectPrimitive.Content>
        </SelectPrimitive.Portal>
    );
}

export function SelectLabel({
    className,
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Label>) {
    return (
        <SelectPrimitive.Label
            className={cn(
                'px-2 py-1.5 text-xs text-muted-foreground',
                className
            )}
            {...props}
        />
    );
}

export function SelectItem({
    className,
    children,
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Item>) {
    return (
        <SelectPrimitive.Item
            className={cn(
                "relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 *:[span]:last:flex *:[span]:last:items-center *:[span]:last:gap-2",
                className
            )}
            {...props}
        >
            <span className="absolute right-2 flex size-3.5 items-center justify-center">
                <SelectPrimitive.ItemIndicator>
                    <CheckIcon className="size-4" />
                </SelectPrimitive.ItemIndicator>
            </span>
            <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
        </SelectPrimitive.Item>
    );
}

export function SelectSeparator({
    className,
    ...props
}: React.ComponentProps<typeof SelectPrimitive.Separator>) {
    return (
        <SelectPrimitive.Separator
            className={cn(
                'pointer-events-none -mx-1 my-1 h-px bg-border',
                className
            )}
            {...props}
        />
    );
}

export type SimpleSelectOption<T extends string> = {
    value: T;
    label: React.ReactNode;
    disabled?: boolean;
    /** Text matched by the search, when `label` is not a plain string */
    searchText?: string;
};

/**
 * Select for simple option lists, with a search field to filter the options
 * (a combobox: a Radix `Select` cannot hold a text input).
 */
export function SimpleSelect<T extends string>({
    value,
    onValueChange,
    options,
    placeholder,
    className,
    disabled,
    size = 'default',
    id,
}: {
    value: T | undefined;
    onValueChange: (value: T) => void;
    options: SimpleSelectOption<T>[];
    placeholder?: string;
    className?: string;
    disabled?: boolean;
    size?: 'sm' | 'default';
    id?: string;
}) {
    const {t} = useTranslation();
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const listId = React.useId();
    const selected = options.find(o => o.value === value);
    const query = search.trim().toLowerCase();
    const visible = query
        ? options.filter(o => optionText(o).toLowerCase().includes(query))
        : options;

    return (
        <Popover
            open={open}
            onOpenChange={o => {
                setOpen(o);
                if (!o) {
                    setSearch('');
                }
            }}
        >
            <PopoverTrigger asChild>
                <button
                    id={id}
                    type="button"
                    role="combobox"
                    aria-expanded={open}
                    aria-controls={listId}
                    data-size={size}
                    data-slot="select-trigger"
                    data-placeholder={selected ? undefined : ''}
                    disabled={disabled}
                    className={cn(
                        "flex w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-left text-sm whitespace-nowrap shadow-xs outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/60 disabled:cursor-not-allowed disabled:opacity-50 data-[placeholder]:text-muted-foreground data-[size=default]:h-9 data-[size=sm]:h-8 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
                        className
                    )}
                >
                    <span className="flex min-w-0 flex-1 items-center gap-2 truncate">
                        {selected ? selected.label : placeholder}
                    </span>
                    <ChevronDownIcon className="size-4 opacity-50" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                id={listId}
                align="start"
                className="z-[70] w-[var(--radix-popover-trigger-width)] min-w-48 p-0"
            >
                <Command shouldFilter={false}>
                    <CommandInput
                        value={search}
                        onValueChange={setSearch}
                        placeholder={t('common.search', 'Search…')}
                    />
                    <CommandList>
                        <CommandEmpty>
                            {t('common.no_match', 'No match')}
                        </CommandEmpty>
                        <CommandGroup>
                            {visible.map(o => (
                                <CommandItem
                                    key={o.value}
                                    value={o.value}
                                    disabled={o.disabled}
                                    data-slot="select-item"
                                    onSelect={() => {
                                        onValueChange(o.value);
                                        setOpen(false);
                                        setSearch('');
                                    }}
                                >
                                    <span className="flex min-w-0 flex-1 items-center gap-2 truncate">
                                        {o.label}
                                    </span>
                                    <CheckIcon
                                        className={cn(
                                            'size-4',
                                            o.value === value
                                                ? 'opacity-100'
                                                : 'opacity-0'
                                        )}
                                    />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

function optionText(o: SimpleSelectOption<string>): string {
    return o.searchText ?? (nodeText(o.label) || o.value);
}

/** Plain text of a React node (the strings it renders) */
function nodeText(node: React.ReactNode): string {
    if (typeof node === 'string' || typeof node === 'number') {
        return String(node);
    }
    if (Array.isArray(node)) {
        return node.map(nodeText).join(' ');
    }
    if (React.isValidElement<{children?: React.ReactNode}>(node)) {
        return nodeText(node.props.children);
    }

    return '';
}

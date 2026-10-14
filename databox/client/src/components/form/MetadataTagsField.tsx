'use client';

import {useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {Command as CommandPrimitive} from 'cmdk';
import {CheckIcon, ChevronRightIcon, PlusIcon} from 'lucide-react';
import {Popover, PopoverAnchor, PopoverContent} from '@/components/ui/overlays';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {Badge, Chip} from '@/components/ui/misc';
import {Spinner} from '@/components/ui/loader';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';
import {getMetadataTags} from '@/lib/api/metadata';
import {cn} from '@/lib/utils/cn';

type Props = {
    value: string[];
    onChange: (value: string[]) => void;
    /** Tags exiftool cannot write are listed but not selectable */
    writableOnly?: boolean;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    id?: string;
};

/**
 * Ordered list of metadata tag names (e.g. "IPTC:Keywords") with suggestions
 * from the exiftool dictionary: the namespaces first, then the tags of the
 * namespace once it is picked (or typed followed by a colon).
 */
export function MetadataTagsField({
    value,
    onChange,
    writableOnly,
    placeholder,
    disabled,
    className,
    id,
}: Props) {
    const {t} = useTranslation();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const query = useDebouncedValue(search.trim(), 150);
    const anchorRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const suggestions = useQuery({
        queryKey: ['metadata-tags', query],
        queryFn: ({signal}) => getMetadataTags(query, signal),
        enabled: open,
        staleTime: Infinity,
        placeholderData: keepPreviousData,
    });

    const add = (tag: string) => {
        if (!value.includes(tag)) {
            onChange([...value, tag]);
        }
        setSearch('');
    };

    const typed = search.trim();
    const typedTag = /^[^:\s]+:[^:\s]+$/.test(typed) ? typed : undefined;
    const items = suggestions.data ?? [];
    const canAddTyped =
        !!typedTag &&
        !value.includes(typedTag) &&
        !items.some(i => i.id.toLowerCase() === typedTag.toLowerCase());

    return (
        <Command
            shouldFilter={false}
            className="h-auto overflow-visible bg-transparent"
        >
            <Popover open={open && !disabled} onOpenChange={setOpen}>
                <PopoverAnchor asChild>
                    <div
                        ref={anchorRef}
                        className={cn(
                            'flex min-h-9 w-full flex-wrap items-center gap-1 rounded-md border border-input px-2 py-1 shadow-xs focus-within:ring-2 focus-within:ring-ring/60',
                            disabled && 'cursor-not-allowed opacity-50',
                            className
                        )}
                        onClick={() => inputRef.current?.focus()}
                    >
                        {value.map(tag => (
                            <Chip
                                key={tag}
                                className="font-mono"
                                onRemove={
                                    disabled
                                        ? undefined
                                        : () =>
                                              onChange(
                                                  value.filter(v => v !== tag)
                                              )
                                }
                            >
                                {tag}
                            </Chip>
                        ))}
                        <CommandPrimitive.Input
                            ref={inputRef}
                            id={id}
                            value={search}
                            onValueChange={v => {
                                setSearch(v);
                                setOpen(true);
                            }}
                            onFocus={() => setOpen(true)}
                            onKeyDown={e => {
                                if (
                                    e.key === 'Backspace' &&
                                    !search &&
                                    value.length > 0
                                ) {
                                    onChange(value.slice(0, -1));
                                } else if (e.key === 'Escape') {
                                    setOpen(false);
                                }
                            }}
                            disabled={disabled}
                            placeholder={value.length ? undefined : placeholder}
                            className="h-7 min-w-32 flex-1 bg-transparent font-mono text-xs outline-hidden placeholder:font-sans placeholder:text-muted-foreground"
                        />
                    </div>
                </PopoverAnchor>
                <PopoverContent
                    align="start"
                    className="w-[var(--radix-popover-trigger-width)] min-w-72 p-0"
                    onOpenAutoFocus={e => e.preventDefault()}
                    onInteractOutside={e => {
                        if (
                            anchorRef.current?.contains(e.target as Node | null)
                        ) {
                            e.preventDefault();
                        }
                    }}
                >
                    <CommandList>
                        {suggestions.isLoading ? (
                            <div className="flex justify-center py-4">
                                <Spinner />
                            </div>
                        ) : (
                            <CommandEmpty>
                                {t('common.no_match', 'No match')}
                            </CommandEmpty>
                        )}
                        <CommandGroup>
                            {canAddTyped ? (
                                <CommandItem
                                    value={`__typed_${typedTag}`}
                                    onSelect={() => add(typedTag!)}
                                >
                                    <PlusIcon />
                                    {t(
                                        'metadata_tags.add_typed',
                                        'Add "{{tag}}"',
                                        {tag: typedTag}
                                    )}
                                </CommandItem>
                            ) : null}
                            {items.map(item => {
                                if (!item.name) {
                                    return (
                                        <CommandItem
                                            key={item.id}
                                            value={item.id}
                                            onSelect={() => {
                                                setSearch(`${item.id}:`);
                                                inputRef.current?.focus();
                                            }}
                                        >
                                            <span className="flex-1 truncate font-mono">
                                                {item.id}
                                            </span>
                                            <ChevronRightIcon className="opacity-50" />
                                        </CommandItem>
                                    );
                                }

                                const selected = value.includes(item.id);
                                const readOnly =
                                    writableOnly && item.writable === false;

                                return (
                                    <CommandItem
                                        key={item.id}
                                        value={item.id}
                                        disabled={selected || readOnly}
                                        onSelect={() => add(item.id)}
                                    >
                                        <CheckIcon
                                            className={cn(
                                                !selected && 'opacity-0'
                                            )}
                                        />
                                        <span className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate font-mono">
                                                {item.id}
                                            </span>
                                            {item.description &&
                                            item.description !== item.name ? (
                                                <span className="truncate text-xs text-muted-foreground">
                                                    {item.description}
                                                </span>
                                            ) : null}
                                        </span>
                                        {item.multi ? (
                                            <Badge variant="muted">
                                                {t(
                                                    'metadata_tags.multi',
                                                    'List'
                                                )}
                                            </Badge>
                                        ) : null}
                                        {item.writable === false ? (
                                            <Badge variant="outline">
                                                {t(
                                                    'metadata_tags.read_only',
                                                    'Read-only'
                                                )}
                                            </Badge>
                                        ) : null}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </PopoverContent>
            </Popover>
        </Command>
    );
}

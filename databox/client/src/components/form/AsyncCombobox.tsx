'use client';

import {ReactNode, useEffect, useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery, useQuery} from '@tanstack/react-query';
import {CheckIcon, ChevronsUpDownIcon, PlusIcon, XIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/overlays';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';
import {useInfiniteScroll} from '@/hooks/useInfiniteScroll';
import {cn} from '@/lib/utils/cn';
import {Spinner} from '@/components/ui/loader';

export type ComboOption<T = unknown> = {
    value: string;
    label: string;
    render?: ReactNode;
    item?: T;
};

export type ComboPage<T = unknown> = {
    options: ComboOption<T>[];
    /** Cursor of the next page, none on the last one */
    next?: string;
};

type BaseProps<T> = {
    queryKey: unknown[];
    /** Loads options matching the typed query */
    loadOptions?: (
        query: string,
        signal: AbortSignal
    ) => Promise<ComboOption<T>[]>;
    /**
     * Loads a page of the options matching the typed query (takes precedence
     * over `loadOptions`): the next ones load when scrolling to the end.
     * `cursor` is the `next` of the previous page.
     */
    loadPage?: (
        query: string,
        signal: AbortSignal,
        cursor?: string
    ) => Promise<ComboPage<T>>;
    /** Resolves labels of selected values not present in the current options */
    resolveValue?: (value: string) => Promise<ComboOption<T> | undefined>;
    /**
     * Resolves the labels of all those values at once (takes precedence over
     * `resolveValue`); the unknown ones may be left out.
     */
    resolveValues?: (values: string[]) => Promise<ComboOption<T>[]>;
    /** Listed before the loaded options, whatever the search */
    fixedOptions?: ComboOption<T>[];
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    id?: string;
    /** Resolves to nothing when the creation was cancelled */
    onCreate?: (query: string) => Promise<ComboOption<T> | undefined>;
    createLabel?: (query: string) => string;
    /** Offers the creation entry even with an empty search: `onCreate` then receives `''` */
    createWithoutQuery?: boolean;
};

type SingleProps<T> = BaseProps<T> & {
    multiple?: false;
    value: string | undefined;
    onChange: (value: string | undefined, option?: ComboOption<T>) => void;
};

type MultiProps<T> = BaseProps<T> & {
    multiple: true;
    value: string[];
    onChange: (value: string[], options: ComboOption<T>[]) => void;
};

/**
 * Searchable async select (single or multiple) backed by the API.
 */
export function AsyncCombobox<T = unknown>(
    props: SingleProps<T> | MultiProps<T>
) {
    const {t} = useTranslation();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const query = useDebouncedValue(search, 250);
    const [known, setKnown] = useState<Record<string, ComboOption<T>>>({});

    const paged = !!props.loadPage;
    const listRef = useRef<HTMLDivElement>(null);

    const options = useQuery({
        queryKey: [...props.queryKey, query],
        queryFn: ({signal}) => props.loadOptions?.(query, signal) ?? [],
        enabled: open && !paged,
        staleTime: 30_000,
    });
    const pages = useInfiniteQuery({
        queryKey: [...props.queryKey, 'pages', query],
        queryFn: ({signal, pageParam}) =>
            props.loadPage!(query, signal, pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: open && paged,
        staleTime: 30_000,
    });
    const {hasNextPage, isFetchingNextPage, fetchNextPage} = pages;
    const lastPage = pages.data?.pages[pages.data.pages.length - 1];

    const fixed = props.fixedOptions;
    const loaded = useMemo(() => {
        const list = paged
            ? (pages.data?.pages.flatMap(p => p.options) ?? [])
            : (options.data ?? []);
        const seen = new Set(fixed?.map(o => o.value));

        return list.filter(o => {
            if (seen.has(o.value)) {
                return false;
            }
            seen.add(o.value);

            return true;
        });
    }, [paged, pages.data, options.data, fixed]);
    const all = useMemo(
        () => (fixed ? [...fixed, ...loaded] : loaded),
        [fixed, loaded]
    );
    const isLoading = paged ? pages.isLoading : options.isLoading;
    const canLoadMore = paged && open && hasNextPage && !isFetchingNextPage;

    // A page left empty by a filter of `loadPage`: the next one may match
    useEffect(() => {
        if (canLoadMore && lastPage?.options.length === 0) {
            void fetchNextPage();
        }
    }, [canLoadMore, lastPage, fetchNextPage]);
    const sentinelRef = useInfiniteScroll(
        listRef,
        () => void fetchNextPage(),
        canLoadMore
    );

    const selected: string[] = props.multiple
        ? props.value
        : props.value
          ? [props.value]
          : [];

    const remember = (opt: ComboOption<T>) =>
        setKnown(k => ({...k, [opt.value]: opt}));

    // resolve labels of preselected values
    const unresolved = selected.filter(
        v => !known[v] && !all.some(o => o.value === v)
    );
    const canResolve = !!props.resolveValues || !!props.resolveValue;
    useQuery({
        queryKey: [...props.queryKey, 'resolve', unresolved],
        queryFn: async () => {
            const results = props.resolveValues
                ? await props.resolveValues(unresolved)
                : await Promise.all(
                      unresolved.map(v => props.resolveValue!(v))
                  );
            results.forEach(r => r && remember(r));

            return null;
        },
        enabled: unresolved.length > 0 && canResolve,
    });

    const labelOf = (v: string) =>
        known[v]?.label ?? all.find(o => o.value === v)?.label ?? v;

    const select = (opt: ComboOption<T>) => {
        remember(opt);
        if (props.multiple) {
            const exists = props.value.includes(opt.value);
            const next = exists
                ? props.value.filter(v => v !== opt.value)
                : [...props.value, opt.value];
            props.onChange(
                next,
                next.map(
                    v =>
                        known[v] ??
                        (v === opt.value ? opt : {value: v, label: labelOf(v)})
                )
            );
        } else {
            props.onChange(
                opt.value === props.value ? undefined : opt.value,
                opt
            );
            setOpen(false);
        }
    };

    const create = async () => {
        if (!props.onCreate || (!search.trim() && !props.createWithoutQuery)) {
            return;
        }
        const opt = await props.onCreate(search.trim());
        if (opt) {
            select(opt);
        }
        setSearch('');
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={props.id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    disabled={props.disabled}
                    className={cn(
                        'h-auto min-h-9 w-full justify-between font-normal',
                        props.className
                    )}
                >
                    <span className="flex min-w-0 flex-1 flex-wrap gap-1 text-left">
                        {selected.length === 0 ? (
                            <span className="truncate text-muted-foreground">
                                {props.placeholder ??
                                    t('common.select', 'Select…')}
                            </span>
                        ) : props.multiple ? (
                            selected.map(v => (
                                <span
                                    key={v}
                                    className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs"
                                >
                                    {known[v]?.render ?? labelOf(v)}
                                    <span
                                        role="button"
                                        className="rounded-full hover:bg-foreground/10"
                                        onClick={e => {
                                            e.stopPropagation();
                                            const next = props.value.filter(
                                                x => x !== v
                                            );
                                            props.onChange(
                                                next,
                                                next.map(
                                                    x =>
                                                        known[x] ?? {
                                                            value: x,
                                                            label: labelOf(x),
                                                        }
                                                )
                                            );
                                        }}
                                    >
                                        <XIcon className="size-3" />
                                    </span>
                                </span>
                            ))
                        ) : (
                            <span className="truncate">
                                {known[selected[0]]?.render ??
                                    labelOf(selected[0])}
                            </span>
                        )}
                    </span>
                    <ChevronsUpDownIcon className="shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[var(--radix-popover-trigger-width)] min-w-64 p-0"
                align="start"
            >
                <Command shouldFilter={false}>
                    <CommandInput
                        value={search}
                        onValueChange={setSearch}
                        placeholder={t('common.search', 'Search…')}
                    />
                    <CommandList ref={listRef}>
                        {isLoading ? (
                            <div className="flex justify-center py-4">
                                <Spinner />
                            </div>
                        ) : (
                            <CommandEmpty>
                                {t('common.no_match', 'No match')}
                            </CommandEmpty>
                        )}
                        <CommandGroup>
                            {all.map(opt => (
                                <CommandItem
                                    key={opt.value}
                                    value={opt.value}
                                    onSelect={() => select(opt)}
                                >
                                    <CheckIcon
                                        className={cn(
                                            'size-4',
                                            selected.includes(opt.value)
                                                ? 'opacity-100'
                                                : 'opacity-0'
                                        )}
                                    />
                                    <span className="flex-1 truncate">
                                        {opt.render ?? opt.label}
                                    </span>
                                </CommandItem>
                            ))}
                            {props.onCreate &&
                            (search.trim()
                                ? !all.some(
                                      o =>
                                          o.label.toLowerCase() ===
                                          search.trim().toLowerCase()
                                  )
                                : props.createWithoutQuery) ? (
                                <CommandItem
                                    value={`__create_${search}`}
                                    onSelect={create}
                                >
                                    <PlusIcon className="size-4" />
                                    {props.createLabel?.(search.trim()) ??
                                        t(
                                            'common.create_value',
                                            'Create "{{value}}"',
                                            {value: search.trim()}
                                        )}
                                </CommandItem>
                            ) : null}
                        </CommandGroup>
                        {paged ? (
                            <div ref={sentinelRef} className="h-px" />
                        ) : null}
                        {isFetchingNextPage ? (
                            <div className="flex justify-center py-2">
                                <Spinner />
                            </div>
                        ) : null}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

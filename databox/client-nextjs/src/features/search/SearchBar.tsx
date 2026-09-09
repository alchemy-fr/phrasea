'use client';

import {FormEvent, useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    FolderIcon,
    ImageIcon,
    LayersIcon,
    LocateFixedIcon,
    SearchIcon,
    XIcon,
} from 'lucide-react';
import {useSearch} from './SearchProvider';
import {useResults} from './ResultProvider';
import {getSearchSuggestions} from '@/lib/api/assets';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {cn} from '@/lib/utils/cn';
import {SearchMoreMenu} from './SearchMoreMenu';
import {SortByButton} from './sort/SortByButton';
import {Highlight} from '@/components/ui/highlight';
import type {SearchSuggestion} from '@/types/api';
import {useBrowserLocation} from '@/hooks/useBrowserLocation';
import {debounce} from '@/lib/utils/misc';

export function SearchBar() {
    const {t} = useTranslation();
    const search = useSearch();
    const {loading} = useResults();
    const [value, setValue] = useState(search.query);
    const [focused, setFocused] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [debounced, setDebounced] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);
    const {requestLocation, loading: locating} = useBrowserLocation();

    useEffect(() => {
        setValue(search.query);
        search.setInputQuery(search.query);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search.query]);

    const setDebouncedQuery = useRef(
        debounce((q: string) => setDebounced(q), 200)
    ).current;

    const suggestions = useQuery({
        queryKey: ['suggest', debounced],
        queryFn: ({signal}) => getSearchSuggestions(debounced, signal),
        enabled: focused && debounced.trim().length > 0,
        staleTime: 30_000,
        select: r => r.items,
    });
    const items: SearchSuggestion[] =
        focused && debounced ? (suggestions.data ?? []) : [];
    const showSuggestions = items.length > 0;

    const submit = (e?: FormEvent) => {
        e?.preventDefault();
        search.setQuery(value);
        setFocused(false);
        inputRef.current?.blur();
    };

    const applySuggestion = (s: SearchSuggestion) => {
        if (s.t === 'collection' && s.tId) {
            setValue('');
            search.setInputQuery('');
            search.selectCollection(s.tId);
        } else if (s.t === 'workspace' && s.tId) {
            setValue('');
            search.setInputQuery('');
            search.selectWorkspace(s.tId);
        } else {
            const q = `"${s.name}"`;
            setValue(q);
            search.setQuery(q);
        }
        setFocused(false);
        setActiveIndex(-1);
    };

    const sameAsCurrent = value === search.query;

    return (
        <form
            onSubmit={submit}
            className="flex items-center gap-2"
            role="search"
        >
            <div className="relative flex-1">
                <SearchIcon className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    ref={inputRef}
                    type="search"
                    value={value}
                    autoFocus
                    placeholder={t('search.placeholder', 'Search assets…')}
                    className="h-10 w-full rounded-lg border bg-card pr-9 pl-9 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/60 [&::-webkit-search-cancel-button]:hidden"
                    onChange={e => {
                        setValue(e.target.value);
                        search.setInputQuery(e.target.value);
                        setDebouncedQuery(e.target.value);
                        setActiveIndex(-1);
                    }}
                    onFocus={() => setFocused(true)}
                    onBlur={() => setTimeout(() => setFocused(false), 150)}
                    onKeyDown={e => {
                        // Ctrl+A must select the text, not the asset list
                        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                            e.stopPropagation();
                        }
                        if (!showSuggestions) {
                            return;
                        }
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            setActiveIndex(i =>
                                Math.min(items.length - 1, i + 1)
                            );
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            setActiveIndex(i => Math.max(-1, i - 1));
                        } else if (e.key === 'Enter' && activeIndex >= 0) {
                            e.preventDefault();
                            applySuggestion(items[activeIndex]);
                        } else if (e.key === 'Escape') {
                            setFocused(false);
                        }
                    }}
                />
                {value ? (
                    <button
                        type="button"
                        className="absolute top-1/2 right-2 -translate-y-1/2 rounded-full p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                        aria-label={t('search.clear', 'Clear')}
                        onClick={() => {
                            setValue('');
                            search.setQuery('');
                            inputRef.current?.focus();
                        }}
                    >
                        <XIcon className="size-4" />
                    </button>
                ) : null}

                {showSuggestions ? (
                    <ul
                        className="absolute top-full left-0 z-30 mt-1 max-h-80 w-full overflow-y-auto rounded-md border bg-popover p-1 text-sm shadow-lg animate-in fade-in-0 zoom-in-95"
                        role="listbox"
                    >
                        {items.map((s, i) => (
                            <li
                                key={`${s.t}-${s.id}`}
                                role="option"
                                aria-selected={i === activeIndex}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5',
                                    i === activeIndex
                                        ? 'bg-accent text-accent-foreground'
                                        : 'hover:bg-accent/60'
                                )}
                                onMouseDown={e => e.preventDefault()}
                                onMouseEnter={() => setActiveIndex(i)}
                                onClick={() => applySuggestion(s)}
                            >
                                <SuggestionIcon type={s.t} />
                                <span className="min-w-0 flex-1 truncate">
                                    <Highlight text={s.hl || s.name} />
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {s.tName}
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : null}
            </div>

            <Tooltip
                content={
                    search.geolocation
                        ? t('search.geo.disable', 'Disable "around me"')
                        : t('search.geo.enable', 'Search around me')
                }
            >
                <Button
                    type="button"
                    variant={search.geolocation ? 'secondary' : 'ghost'}
                    size="icon"
                    loading={locating}
                    onClick={async () => {
                        if (search.geolocation) {
                            search.setGeolocation(undefined);

                            return;
                        }
                        const pos = await requestLocation();
                        if (pos) {
                            search.setGeolocation(`${pos.lat},${pos.lng}`);
                        }
                    }}
                    aria-label={t('search.geo.enable', 'Search around me')}
                >
                    <LocateFixedIcon
                        className={cn(search.geolocation && 'text-primary')}
                    />
                </Button>
            </Tooltip>
            <SortByButton />
            <Button
                type="submit"
                disabled={sameAsCurrent && loading}
                className="hidden sm:inline-flex"
            >
                {t('search.submit', 'Search')}
            </Button>
            <SearchMoreMenu />
        </form>
    );
}

function SuggestionIcon({type}: {type: SearchSuggestion['t']}) {
    const cls = 'size-4 shrink-0 text-muted-foreground';
    switch (type) {
        case 'collection':
            return <FolderIcon className={cls} />;
        case 'workspace':
            return <LayersIcon className={cls} />;
        default:
            return <ImageIcon className={cls} />;
    }
}

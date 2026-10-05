'use client';

import {FormEvent, ReactNode, useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    CornerDownLeftIcon,
    FilterIcon,
    FolderIcon,
    ImageIcon,
    LayersIcon,
    ListIcon,
    LocateFixedIcon,
    SearchIcon,
    TagIcon,
    ToggleLeftIcon,
    XIcon,
} from 'lucide-react';
import {useSearch} from './SearchProvider';
import {useResults} from './ResultProvider';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {cn} from '@/lib/utils/cn';
import {SearchMoreMenu} from './SearchMoreMenu';
import {SortByButton} from './sort/SortByButton';
import {Highlight} from '@/components/ui/highlight';
import type {SearchSuggestion} from '@/types/api';
import {AttributeType} from '@/types/api';
import {useBrowserLocation} from '@/hooks/useBrowserLocation';
import {useSearchSuggestions} from './suggest/useSearchSuggestions';
import {
    findConditionId,
    isEntityField,
    SuggestionItem,
} from './suggest/filterSuggestions';

export function SearchBar() {
    const {t} = useTranslation();
    const search = useSearch();
    const {loading, facets} = useResults();
    const [value, setValue] = useState(search.query);
    const [focused, setFocused] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const inputRef = useRef<HTMLInputElement>(null);
    const {requestLocation, loading: locating} = useBrowserLocation();

    useEffect(() => {
        setValue(search.query);
        search.setInputQuery(search.query);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search.query]);

    const {mode, items, valuePrefix} = useSearchSuggestions(value, {
        enabled: focused,
        facets,
    });
    const showSuggestions = focused && items.length > 0;

    const submit = (e?: FormEvent) => {
        e?.preventDefault();
        search.setQuery(value);
        setFocused(false);
        inputRef.current?.blur();
    };

    const setInput = (next: string) => {
        setValue(next);
        search.setInputQuery(next);
        setActiveIndex(-1);
    };

    const applyTextSuggestion = (s: SearchSuggestion) => {
        if (s.t === 'collection' && s.tId) {
            setInput('');
            search.selectCollection(s.tId);
        } else if (s.t === 'workspace' && s.tId) {
            setInput('');
            search.selectWorkspace(s.tId);
        } else {
            const q = `"${s.name}"`;
            setInput(q);
            search.setQuery(q);
        }
        setFocused(false);
    };

    const applyItem = (item: SuggestionItem) => {
        switch (item.kind) {
            case 'text':
                applyTextSuggestion(item.suggestion);
                break;
            case 'field':
                // Go on with the values of the field
                setInput(item.text);
                inputRef.current?.focus();
                break;
            case 'value':
            case 'raw':
                search.upsertCondition({
                    id: findConditionId(search.conditions, item.definition),
                    query: item.query,
                    resetQuery: true,
                });
                setInput('');
                inputRef.current?.focus();
                break;
        }
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
                    data-testid="search-input"
                    value={value}
                    autoFocus
                    placeholder={t('search.placeholder', 'Search assets…')}
                    className="h-10 w-full rounded-lg border bg-card pr-9 pl-9 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/60 [&::-webkit-search-cancel-button]:hidden"
                    onChange={e => setInput(e.target.value)}
                    onFocus={() => setFocused(true)}
                    onBlur={() => setTimeout(() => setFocused(false), 150)}
                    onKeyDown={e => {
                        // Ctrl+A must select the text, not the asset list
                        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                            e.stopPropagation();
                        }
                        if (e.key === 'Enter') {
                            if (showSuggestions && activeIndex >= 0) {
                                e.preventDefault();
                                applyItem(items[activeIndex]);
                            } else if (mode === 'field') {
                                // A `field:` input is a filter being typed,
                                // never a text search: apply the typed value,
                                // or the only value left by the typed prefix
                                e.preventDefault();
                                const raw = items.find(i => i.kind === 'raw');
                                if (raw) {
                                    applyItem(raw);
                                } else if (valuePrefix && items.length === 1) {
                                    applyItem(items[0]);
                                }
                            }

                            return;
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
                        data-testid="search-clear"
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
                        data-testid="search-suggestions"
                    >
                        {items.map((item, i) => (
                            <SuggestionRow
                                key={itemKey(item)}
                                item={item}
                                active={i === activeIndex}
                                separated={
                                    i > 0 &&
                                    item.kind === 'text' &&
                                    items[i - 1].kind === 'field'
                                }
                                onHover={() => setActiveIndex(i)}
                                onSelect={() => applyItem(item)}
                            />
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
                    data-testid="search-geo"
                >
                    <LocateFixedIcon
                        className={cn(search.geolocation && 'text-primary')}
                    />
                </Button>
            </Tooltip>
            <SortByButton />
            <Button
                type="submit"
                data-testid="search-submit"
                disabled={sameAsCurrent && loading}
                className="hidden sm:inline-flex"
            >
                {t('search.submit', 'Search')}
            </Button>
            <SearchMoreMenu />
        </form>
    );
}

function itemKey(item: SuggestionItem): string {
    switch (item.kind) {
        case 'text':
            return `text-${item.suggestion.t}-${item.suggestion.id}`;
        case 'field':
            return `field-${item.key}`;
        case 'value':
            return `value-${item.field}-${String(item.value)}`;
        case 'raw':
            return `raw-${item.field}`;
    }
}

function SuggestionRow({
    item,
    active,
    separated,
    onHover,
    onSelect,
}: {
    item: SuggestionItem;
    active: boolean;
    separated: boolean;
    onHover: () => void;
    onSelect: () => void;
}) {
    const {t} = useTranslation();
    const ref = useRef<HTMLLIElement>(null);
    // Keyboard navigation: keep the highlighted item visible in the list
    useEffect(() => {
        if (active) {
            ref.current?.scrollIntoView?.({block: 'nearest'});
        }
    }, [active]);

    let icon: ReactNode;
    let main: ReactNode;
    let aside: ReactNode;
    let title: string | undefined;
    switch (item.kind) {
        case 'text':
            icon = <SuggestionIcon type={item.suggestion.t} />;
            main = (
                <Highlight text={item.suggestion.hl || item.suggestion.name} />
            );
            aside = item.suggestion.tName;
            break;
        case 'field':
            icon = <FilterIcon className={ICON_CLASS} />;
            main = <Highlight text={item.hl} />;
            aside = (
                <>
                    {item.definition.displayName}
                    <span className="ml-1 opacity-70">
                        {item.definition.builtIn
                            ? t('search.condition.built_in', 'Built-in')
                            : t('search.condition.attributes', 'Attributes')}
                    </span>
                </>
            );
            title = t('search.suggest.field_hint', 'Filter by {{name}}', {
                name: item.definition.displayName,
            });
            break;
        case 'value':
            icon =
                item.definition.type === AttributeType.Boolean ? (
                    <ToggleLeftIcon className={ICON_CLASS} />
                ) : isEntityField(item.definition) ? (
                    <TagIcon className={ICON_CLASS} />
                ) : (
                    <ListIcon className={ICON_CLASS} />
                );
            main = <Highlight text={item.hl || item.label} />;
            aside = item.definition.displayName;
            break;
        case 'raw':
            icon = <CornerDownLeftIcon className={ICON_CLASS} />;
            main = <span className="font-mono text-xs">{item.query}</span>;
            aside = t('search.suggest.press_enter', 'Enter');
            break;
    }

    return (
        <li
            ref={ref}
            role="option"
            aria-selected={active}
            title={title}
            data-testid={`search-suggestion-${item.kind}`}
            className={cn(
                'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5',
                active
                    ? 'bg-accent text-accent-foreground'
                    : 'hover:bg-accent/60',
                separated && 'mt-1 border-t pt-2'
            )}
            onMouseDown={e => e.preventDefault()}
            onMouseEnter={onHover}
            onClick={onSelect}
        >
            {icon}
            <span className="min-w-0 flex-1 truncate">{main}</span>
            <span className="shrink-0 text-xs text-muted-foreground">
                {aside}
            </span>
        </li>
    );
}

const ICON_CLASS = 'size-4 shrink-0 text-muted-foreground';

function SuggestionIcon({type}: {type: SearchSuggestion['t']}) {
    switch (type) {
        case 'collection':
            return <FolderIcon className={ICON_CLASS} />;
        case 'workspace':
            return <LayersIcon className={ICON_CLASS} />;
        default:
            return <ImageIcon className={ICON_CLASS} />;
    }
}

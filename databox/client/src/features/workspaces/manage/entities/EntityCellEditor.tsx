'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {XIcon} from 'lucide-react';
import {Input} from '@alchemy/next-ui/ui/input';
import {TagsInput} from '@/components/form/TagsInput';
import {cn} from '@alchemy/next-ui/utils/cn';
import type {CellEditorRenderProps} from '@/components/ui/data-table/types';
import {parseSynonyms} from './entityColumns';

/**
 * In-cell editors of the entity grid. They commit on Enter (in an empty
 * field for the chips), Tab and blur; Escape is handled by the grid (cancel).
 */
export function EntityCellEditor<T>(p: CellEditorRenderProps<T>) {
    switch (p.kind) {
        case 'select':
            return <SelectEditor {...p} />;
        case 'color':
            return <ColorEditor {...p} />;
        case 'tags':
            return <TagsEditor {...p} />;
        default:
            return <TextEditor {...p} />;
    }
}

function TextEditor<T>({
    kind,
    value,
    initialValue,
    onCommit,
    onCancel,
}: CellEditorRenderProps<T>) {
    const [text, setText] = useState(initialValue ?? value);
    const ref = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const input = ref.current;
        if (!input) {
            return;
        }
        input.focus();
        if (initialValue === undefined) {
            input.select();
        } else {
            input.setSelectionRange(input.value.length, input.value.length);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <Input
            ref={ref}
            value={text}
            onChange={e => setText(e.target.value)}
            onBlur={() => onCommit(text)}
            onKeyDown={e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    onCommit(text);
                } else if (e.key === 'Escape') {
                    e.stopPropagation();
                    onCancel();
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    onCommit(text);
                }
            }}
            maxLength={kind === 'emoji' ? 4 : undefined}
            className={cn(
                'h-7 px-1 text-sm',
                kind === 'emoji' && 'w-12 text-center'
            )}
        />
    );
}

function SelectEditor<T>({
    value,
    options,
    onCommit,
    onCancel,
}: CellEditorRenderProps<T>) {
    return (
        <select
            autoFocus
            defaultValue={value}
            onChange={e => onCommit(e.target.value)}
            onBlur={e => onCommit(e.target.value)}
            onKeyDown={e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    onCommit(e.currentTarget.value);
                } else if (e.key === 'Escape') {
                    e.stopPropagation();
                    onCancel();
                }
            }}
            className="h-7 w-full rounded-md border border-input bg-background px-1 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/60"
        >
            {options?.map(o => (
                <option key={o.value} value={o.value}>
                    {typeof o.label === 'string' ? o.label : o.value}
                </option>
            ))}
        </select>
    );
}

function ColorEditor<T>({value, onCommit, onCancel}: CellEditorRenderProps<T>) {
    const {t} = useTranslation();
    // Empty until picked: leaving the editor must not set the default black
    const [color, setColor] = useState(value);

    return (
        <div
            className="flex items-center gap-1"
            onBlur={e => {
                if (!e.currentTarget.contains(e.relatedTarget as Node | null)) {
                    onCommit(color);
                }
            }}
            onKeyDown={e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    onCommit(color);
                } else if (e.key === 'Escape') {
                    e.stopPropagation();
                    onCancel();
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    onCommit(color);
                }
            }}
        >
            <input
                type="color"
                autoFocus
                value={color || '#000000'}
                onChange={e => setColor(e.target.value)}
                className="h-7 w-10 cursor-pointer rounded border bg-background"
            />
            <button
                type="button"
                aria-label={t('table.clear_color', 'Clear the color')}
                title={t('table.clear_color', 'Clear the color')}
                onClick={() => onCommit('')}
                className="flex size-5 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent hover:text-foreground"
            >
                <XIcon className="size-3.5" />
            </button>
        </div>
    );
}

/**
 * A JSON list edited as chips; Enter in the empty text field, Tab and leaving
 * the editor commit the list.
 */
function TagsEditor<T>({
    value,
    initialValue,
    onCommit,
    onCancel,
}: CellEditorRenderProps<T>) {
    const {t} = useTranslation();
    const [list, setList] = useState<string[]>(
        () => parseSynonyms(value) ?? []
    );
    // Read by the commits: the input adds its pending text on blur and Tab,
    // in the same event as the one bubbling to the container, before React
    // renders the new list
    const listRef = useRef(list);
    const change = (next: string[]) => {
        listRef.current = next;
        setList(next);
    };
    const commit = () => onCommit(JSON.stringify(listRef.current));

    return (
        <div
            className="w-full"
            // Keeps the focus in the text field when a chip is removed
            onMouseDown={e => {
                if (!(e.target instanceof HTMLInputElement)) {
                    e.preventDefault();
                }
            }}
            onBlur={e => {
                if (!e.currentTarget.contains(e.relatedTarget as Node | null)) {
                    commit();
                }
            }}
            onKeyDown={e => {
                if (e.key === 'Escape') {
                    e.stopPropagation();
                    onCancel();
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    commit();
                }
            }}
        >
            <TagsInput
                value={list}
                onChange={change}
                splitOn={/\r?\n/}
                autoFocus
                initialText={initialValue}
                onSubmit={commit}
                placeholder={t(
                    'entity.synonyms_placeholder',
                    'Type a synonym, then Enter'
                )}
                className="min-h-7 flex-nowrap overflow-x-auto py-0.5"
            />
        </div>
    );
}

'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {XIcon} from 'lucide-react';
import {Input} from '@/components/ui/input';
import {cn} from '@/lib/utils/cn';
import type {CellEditorRenderProps} from '@/components/ui/data-table/types';

/**
 * In-cell editors of the entity grid. They commit on Enter, Tab and blur;
 * Escape is handled by the grid (cancel).
 */
export function EntityCellEditor<T>(p: CellEditorRenderProps<T>) {
    switch (p.kind) {
        case 'select':
            return <SelectEditor {...p} />;
        case 'color':
            return <ColorEditor {...p} />;
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

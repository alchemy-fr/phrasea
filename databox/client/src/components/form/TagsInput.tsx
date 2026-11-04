'use client';

import {ClipboardEvent, KeyboardEvent, useState} from 'react';
import {XIcon} from 'lucide-react';
import {cn} from '@/lib/utils/cn';

type Props = {
    'value': string[];
    'onChange': (value: string[]) => void;
    'id'?: string;
    'placeholder'?: string;
    'disabled'?: boolean;
    'className'?: string;
    'aria-invalid'?: boolean;
};

const separators = /[,\s]+/;

/**
 * List of free values as chips: Enter or a comma adds the typed one,
 * Backspace on an empty field removes the last one, a paste is split on
 * commas and spaces.
 */
export function TagsInput({
    value,
    onChange,
    id,
    placeholder,
    disabled,
    className,
    'aria-invalid': invalid,
}: Props) {
    const [text, setText] = useState('');

    const add = (items: string[]) => {
        const added = items.map(s => s.trim()).filter(Boolean);
        if (added.length === 0) {
            return;
        }
        onChange([...value, ...added.filter(s => !value.includes(s))]);
    };

    const commit = () => {
        add([text]);
        setText('');
    };

    const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            commit();
        } else if (e.key === 'Backspace' && text === '' && value.length > 0) {
            e.preventDefault();
            onChange(value.slice(0, -1));
        }
    };

    const onPaste = (e: ClipboardEvent<HTMLInputElement>) => {
        const pasted = e.clipboardData.getData('text');
        if (!separators.test(pasted)) {
            return;
        }
        e.preventDefault();
        add(pasted.split(separators));
        setText('');
    };

    return (
        <div
            data-slot="tags-input"
            aria-invalid={invalid}
            className={cn(
                'flex min-h-9 w-full flex-wrap items-center gap-1 rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-xs transition-colors focus-within:ring-2 focus-within:ring-ring/60 aria-invalid:border-destructive',
                disabled && 'cursor-not-allowed opacity-50',
                className
            )}
        >
            {value.map((item, index) => (
                <span
                    key={`${item}-${index}`}
                    data-testid="tags-input-item"
                    className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs"
                >
                    {item}
                    <button
                        type="button"
                        aria-label={`Remove ${item}`}
                        disabled={disabled}
                        className="rounded-full hover:bg-foreground/10"
                        onClick={() =>
                            onChange(value.filter((_, i) => i !== index))
                        }
                    >
                        <XIcon className="size-3" />
                    </button>
                </span>
            ))}
            <input
                id={id}
                value={text}
                disabled={disabled}
                placeholder={value.length === 0 ? placeholder : undefined}
                className="h-6 min-w-24 flex-1 bg-transparent outline-none placeholder:text-muted-foreground"
                onChange={e => setText(e.target.value)}
                onKeyDown={onKeyDown}
                onPaste={onPaste}
                onBlur={commit}
            />
        </div>
    );
}

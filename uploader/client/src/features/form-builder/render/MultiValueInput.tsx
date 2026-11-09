'use client';

import {useState} from 'react';
import {Input} from '@alchemy/next-ui/ui/input';
import {Chip} from '@alchemy/next-ui/ui/misc';
import {cn} from '@alchemy/next-ui/utils/cn';

/**
 * Several values typed one by one: Enter (or a comma for texts) adds the
 * typed one, Backspace on an empty input removes the last one.
 */
export function MultiValueInput({
    id,
    value,
    onChange,
    type = 'text',
    placeholder,
    disabled,
    invalid,
}: {
    id?: string;
    value: string[];
    onChange: (value: string[]) => void;
    type?: string;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
}) {
    const [draft, setDraft] = useState('');

    const add = () => {
        const v = draft.trim();
        if (v && !value.includes(v)) {
            onChange([...value, v]);
        }
        setDraft('');
    };

    return (
        <div
            className={cn(
                'flex min-h-9 w-full flex-wrap items-center gap-1.5 rounded-md border border-input px-2 py-1 shadow-xs focus-within:ring-2 focus-within:ring-ring/60',
                invalid && 'border-destructive',
                disabled && 'opacity-50'
            )}
        >
            {value.map(v => (
                <Chip
                    key={v}
                    onRemove={
                        disabled
                            ? undefined
                            : () => onChange(value.filter(x => x !== v))
                    }
                >
                    {v}
                </Chip>
            ))}
            <Input
                id={id}
                type={type}
                value={draft}
                disabled={disabled}
                placeholder={value.length === 0 ? placeholder : undefined}
                aria-invalid={invalid || undefined}
                className="h-7 min-w-24 flex-1 border-0 px-1 shadow-none focus-visible:ring-0"
                onChange={e => setDraft(e.target.value)}
                onBlur={add}
                onKeyDown={e => {
                    if (
                        e.key === 'Enter' ||
                        (e.key === ',' && type === 'text')
                    ) {
                        e.preventDefault();
                        add();
                    } else if (
                        e.key === 'Backspace' &&
                        draft === '' &&
                        value.length > 0
                    ) {
                        onChange(value.slice(0, -1));
                    }
                }}
            />
        </div>
    );
}

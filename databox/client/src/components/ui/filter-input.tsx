'use client';

import * as React from 'react';
import {useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {XIcon} from 'lucide-react';
import {Input} from './input';
import {cn} from '@/lib/utils/cn';

/**
 * Text input filtering a list, with a clear button once filled. Escape also
 * clears it (and is not propagated, so a dialog around it stays open).
 */
export function FilterInput({
    value,
    onValueChange,
    className,
    placeholder,
    onKeyDown,
    ...props
}: Omit<React.ComponentProps<'input'>, 'value' | 'onChange'> & {
    value: string;
    onValueChange: (value: string) => void;
}) {
    const {t} = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);

    const clear = () => {
        onValueChange('');
        inputRef.current?.focus();
    };

    return (
        <div className={cn('relative w-full min-w-0', className)}>
            <Input
                {...props}
                ref={inputRef}
                value={value}
                onChange={e => onValueChange(e.target.value)}
                onKeyDown={e => {
                    if (e.key === 'Escape' && value) {
                        e.preventDefault();
                        e.stopPropagation();
                        clear();
                    }
                    onKeyDown?.(e);
                }}
                placeholder={placeholder ?? t('common.filter', 'Filter…')}
                className={cn('h-8', value && 'pr-8')}
            />
            {value ? (
                <button
                    type="button"
                    onClick={clear}
                    aria-label={t('common.clear', 'Clear')}
                    className="absolute top-1/2 right-1.5 flex size-5 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground outline-none transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60"
                >
                    <XIcon className="size-3.5" />
                </button>
            ) : null}
        </div>
    );
}

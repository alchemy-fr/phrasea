'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Input, Textarea} from '@alchemy/next-ui/ui/input';
import {cn} from '@alchemy/next-ui/utils/cn';
import {DEFAULT_LOCALE, type Localized} from '../types';

/**
 * A text in every language of the form: `_` is used for the languages that
 * have no text of their own.
 */
export function LocalizedInput({
    id,
    value,
    onChange,
    locales,
    multiline,
    placeholder,
    disabled,
}: {
    id?: string;
    value: Localized | undefined;
    onChange: (value: Localized) => void;
    locales: string[];
    multiline?: boolean;
    placeholder?: string;
    disabled?: boolean;
}) {
    const {t} = useTranslation();
    const [locale, setLocale] = useState(DEFAULT_LOCALE);
    const all = [DEFAULT_LOCALE, ...locales.filter(l => l !== DEFAULT_LOCALE)];
    const text = value?.[locale] ?? '';

    const set = (next: string) => {
        const copy = {...value};
        if (next) {
            copy[locale] = next;
        } else {
            delete copy[locale];
        }
        onChange(copy);
    };

    const Control = multiline ? Textarea : Input;

    return (
        <div>
            <div
                role="tablist"
                className="mb-1 flex flex-wrap gap-1"
                aria-label={t('builder.locales', 'Languages')}
            >
                {all.map(l => (
                    <button
                        key={l}
                        type="button"
                        role="tab"
                        aria-selected={l === locale}
                        onClick={() => setLocale(l)}
                        className={cn(
                            'rounded px-1.5 py-0.5 text-xs uppercase text-muted-foreground hover:bg-muted',
                            l === locale &&
                                'bg-muted font-semibold text-foreground',
                            !value?.[l] && 'italic opacity-70'
                        )}
                    >
                        {l === DEFAULT_LOCALE
                            ? t('builder.all_locales', 'All')
                            : l}
                    </button>
                ))}
            </div>
            <Control
                id={id}
                value={text}
                disabled={disabled}
                placeholder={
                    locale === DEFAULT_LOCALE
                        ? placeholder
                        : (value?.[DEFAULT_LOCALE] ?? placeholder)
                }
                onChange={e => set(e.target.value)}
            />
        </div>
    );
}

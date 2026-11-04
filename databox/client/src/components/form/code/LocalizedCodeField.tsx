'use client';

import {useId, useState} from 'react';
import {Label} from '@/components/ui/input';
import {LocaleTabs, NO_LOCALE} from '@/components/form/TranslatableField';
import {CodeEditor, CodeEditorMode} from './CodeEditor';

/**
 * Code value per locale (`{_: …, fr: …}`), one tab each, like the
 * translatable fields: the `_` one applies to every locale without its own.
 */
export function LocalizedCodeField({
    label,
    help,
    values,
    onChange,
    locales,
    mode = 'twig',
    placeholder,
}: {
    label: string;
    help?: string;
    values: Record<string, string>;
    onChange: (values: Record<string, string>) => void;
    locales: string[];
    mode?: CodeEditorMode;
    placeholder?: string;
}) {
    const id = useId();
    const [tab, setTab] = useState(NO_LOCALE);
    const active = tab === NO_LOCALE || locales.includes(tab) ? tab : NO_LOCALE;

    return (
        <div className="min-w-0">
            <Label htmlFor={id} className="mb-1.5">
                {label}
            </Label>
            <LocaleTabs
                value={active}
                onChange={setTab}
                locales={locales}
                filled={Object.keys(values).filter(k => values[k])}
            />
            <CodeEditor
                // One editor per tab: each keeps its own undo history
                key={active}
                id={id}
                mode={mode}
                minLines={4}
                maxLines={20}
                placeholder={placeholder}
                value={values[active] ?? ''}
                onChange={v => {
                    const next = {...values};
                    if (v) {
                        next[active] = v;
                    } else {
                        delete next[active];
                    }
                    onChange(next);
                }}
            />
            {help ? (
                <p className="mt-1 text-xs text-muted-foreground">{help}</p>
            ) : null}
        </div>
    );
}

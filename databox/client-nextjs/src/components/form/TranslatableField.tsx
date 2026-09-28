'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Input, Label} from '@/components/ui/input';
import {Tabs, TabsList, TabsTrigger} from '@/components/ui/misc';
import {Flag} from '@/components/ui/flag';

/** Key of the value that is not tied to a locale */
export const NO_LOCALE = '_';

/**
 * Tabs of a translatable field: the default value, then one per locale, with
 * its flag. Nothing when there is no locale.
 */
export function LocaleTabs({
    value,
    onChange,
    locales,
    defaultLabel,
    filled,
}: {
    value: string;
    onChange: (tab: string) => void;
    locales: string[];
    defaultLabel?: string;
    /** Tabs holding a value, marked with a dot */
    filled?: string[];
}) {
    const {t} = useTranslation();
    if (locales.length === 0) {
        return null;
    }
    const dot = (tab: string) =>
        filled?.includes(tab) ? (
            <span className="size-1.5 rounded-full bg-primary" aria-hidden />
        ) : null;

    return (
        <Tabs value={value} onValueChange={onChange} className="mb-2">
            <TabsList className="h-8">
                <TabsTrigger value={NO_LOCALE} className="h-6 px-2 text-xs">
                    {defaultLabel ?? t('form.translatable.default', 'Default')}
                    {dot(NO_LOCALE)}
                </TabsTrigger>
                {locales.map(l => (
                    <TabsTrigger key={l} value={l} className="h-6 px-2 text-xs">
                        <Flag locale={l} /> {l}
                        {dot(l)}
                    </TabsTrigger>
                ))}
            </TabsList>
        </Tabs>
    );
}

/**
 * Text field with one tab per workspace locale (default value + translations).
 */
export function TranslatableField({
    label,
    value,
    onChange,
    translations,
    onTranslationsChange,
    locales,
    id = 'translatable',
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    translations: Record<string, string>;
    onTranslationsChange: (v: Record<string, string>) => void;
    locales: string[];
    id?: string;
}) {
    const [tab, setTab] = useState(NO_LOCALE);

    return (
        <div className="mb-4">
            <Label htmlFor={id} className="mb-1.5">
                {label}
            </Label>
            <LocaleTabs value={tab} onChange={setTab} locales={locales} />
            {tab === NO_LOCALE ? (
                <Input
                    id={id}
                    value={value}
                    onChange={e => onChange(e.target.value)}
                />
            ) : (
                <Input
                    id={id}
                    value={translations[tab] ?? ''}
                    placeholder={value}
                    onChange={e => {
                        const next = {...translations};
                        if (e.target.value) {
                            next[tab] = e.target.value;
                        } else {
                            delete next[tab];
                        }
                        onTranslationsChange(next);
                    }}
                />
            )}
        </div>
    );
}

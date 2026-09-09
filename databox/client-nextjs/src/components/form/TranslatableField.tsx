'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Input, Label} from '@/components/ui/input';
import {Tabs, TabsList, TabsTrigger} from '@/components/ui/misc';
import {Flag} from '@/components/ui/flag';

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
    const {t} = useTranslation();
    const [tab, setTab] = useState('_');

    return (
        <div className="mb-4">
            <Label htmlFor={id} className="mb-1.5">
                {label}
            </Label>
            {locales.length > 0 ? (
                <Tabs value={tab} onValueChange={setTab} className="mb-2">
                    <TabsList className="h-8">
                        <TabsTrigger value="_" className="h-6 px-2 text-xs">
                            {t('form.translatable.default', 'Default')}
                        </TabsTrigger>
                        {locales.map(l => (
                            <TabsTrigger
                                key={l}
                                value={l}
                                className="h-6 px-2 text-xs"
                            >
                                <Flag locale={l} /> {l}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>
            ) : null}
            {tab === '_' ? (
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

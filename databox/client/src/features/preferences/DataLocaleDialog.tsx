'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {CheckIcon} from 'lucide-react';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {getLocales} from '@/lib/api/metadata';
import {usePreferencesStore} from './store';
import {setApiLocales} from '@/lib/api/http';

export function DataLocaleDialog({open, onOpenChange}: ModalProps) {
    const {t} = useTranslation();
    const current = usePreferencesStore(s => s.preferences.dataLocale);
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const [selected, setSelected] = useState<string | undefined>(current);
    const locales = useQuery({
        queryKey: ['locales'],
        queryFn: getLocales,
        staleTime: Infinity,
    });

    const apply = async () => {
        await updatePreference('dataLocale', selected);
        setApiLocales({data: selected});
        if (selected !== current) {
            window.location.reload();

            // Keep the dialog up until the reload happens
            return false;
        }
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('settings.data_locale', 'Data language')}
            description={t(
                'settings.data_locale_help',
                'Language used for attribute values and translated names. Changing it reloads the page.'
            )}
            submitLabel={t('common.apply', 'Apply')}
            onSubmit={apply}
        >
            <Command className="rounded-md border">
                <CommandInput placeholder={t('common.search', 'Search…')} />
                <CommandList className="max-h-64">
                    <CommandEmpty>
                        {t('common.no_match', 'No match')}
                    </CommandEmpty>
                    <CommandGroup>
                        <CommandItem
                            value="__default"
                            onSelect={() => setSelected(undefined)}
                        >
                            {selected === undefined ? (
                                <CheckIcon />
                            ) : (
                                <span className="size-4" />
                            )}
                            {t(
                                'settings.data_locale_default',
                                'Default — use UI language'
                            )}
                        </CommandItem>
                        {(locales.data ?? []).map(l => (
                            <CommandItem
                                key={l.id}
                                value={`${l.name} ${l.nativeName} ${l.id}`}
                                onSelect={() => setSelected(l.id)}
                            >
                                {selected === l.id ? (
                                    <CheckIcon />
                                ) : (
                                    <span className="size-4" />
                                )}
                                <span className="flex-1">{l.name}</span>
                                <span className="text-xs text-muted-foreground">
                                    {l.nativeName}
                                </span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                </CommandList>
            </Command>
        </FormDialog>
    );
}

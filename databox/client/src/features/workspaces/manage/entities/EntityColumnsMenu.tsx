'use client';

import {type ReactNode, useCallback, useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Columns3Icon} from 'lucide-react';
import type {EntityList} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@alchemy/next-ui/ui/menu';
import {Flag} from '@/components/ui/flag';

const TRANSLATIONS = 'translations.';
const SYNONYMS = 'synonyms.';
/** Hides the synonyms of every locale */
export const SYNONYMS_OPTION = 'synonyms';

const storageKey = (listId: string) => `dbx.entityColumns.${listId}`;

/**
 * Whether a column of the values table is shown: the synonyms of a locale
 * follow both the synonyms option and the column of that locale.
 */
export function isEntityColumnVisible(
    id: string,
    hidden: ReadonlySet<string>
): boolean {
    if (id.startsWith(SYNONYMS)) {
        return (
            !hidden.has(SYNONYMS_OPTION) &&
            !hidden.has(`${TRANSLATIONS}${id.slice(SYNONYMS.length)}`)
        );
    }

    return !hidden.has(id);
}

/** The columns hidden by the user, remembered per list on this device */
export function useHiddenEntityColumns(listId: string) {
    const [hidden, setHidden] = useState<ReadonlySet<string>>(() => new Set());

    // After hydration: the server render must not read the local storage
    useEffect(() => {
        try {
            const stored = JSON.parse(
                window.localStorage.getItem(storageKey(listId)) ?? '[]'
            );
            setHidden(
                new Set(
                    Array.isArray(stored)
                        ? stored.filter(
                              (s): s is string => typeof s === 'string'
                          )
                        : []
                )
            );
        } catch {
            setHidden(new Set());
        }
    }, [listId]);

    const toggle = useCallback(
        (option: string, visible: boolean) =>
            setHidden(current => {
                const next = new Set(current);
                if (visible) {
                    next.delete(option);
                } else {
                    next.add(option);
                }
                try {
                    window.localStorage.setItem(
                        storageKey(listId),
                        JSON.stringify([...next])
                    );
                } catch {
                    // Not remembered
                }

                return next;
            }),
        [listId]
    );

    return {hidden, toggle};
}

/** Shows or hides the optional columns of the values table */
export function EntityColumnsMenu({
    list,
    locales,
    hidden,
    onToggle,
    withPath,
}: {
    list: EntityList;
    /** The path column is shown with the search results of a hierarchy */
    withPath?: boolean;
    locales: string[];
    hidden: ReadonlySet<string>;
    onToggle: (option: string, visible: boolean) => void;
}) {
    const {t} = useTranslation();
    const withLocales = !!(list.withTranslations || list.withSynonyms);

    const item = (option: string, label: ReactNode) => (
        <DropdownMenuCheckboxItem
            key={option}
            checked={!hidden.has(option)}
            // Stays open: several columns are usually toggled
            onSelect={e => e.preventDefault()}
            onCheckedChange={v => onToggle(option, v === true)}
            data-testid={`entity-column-${option}`}
        >
            {label}
        </DropdownMenuCheckboxItem>
    );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    title={t('entity.columns', 'Columns')}
                    aria-label={t('entity.columns', 'Columns')}
                    data-testid="entity-columns"
                >
                    <Columns3Icon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuLabel>
                    {t('entity.columns', 'Columns')}
                </DropdownMenuLabel>
                {withPath ? item('path', t('entity.path', 'Path')) : null}
                {item('note', t('entity.note', 'Note'))}
                {withLocales && locales.length > 0 ? (
                    <>
                        <DropdownMenuSeparator />
                        {locales.map(locale =>
                            item(
                                `${TRANSLATIONS}${locale}`,
                                <span className="flex items-center gap-1">
                                    <Flag locale={locale} /> {locale}
                                </span>
                            )
                        )}
                        {list.withSynonyms
                            ? item(
                                  SYNONYMS_OPTION,
                                  t('entity.synonyms', 'Synonyms')
                              )
                            : null}
                        <DropdownMenuSeparator />
                    </>
                ) : null}
                {item('usage', t('entity.usage', 'Usage'))}
                {item('updatedAt', t('entity.updated_at', 'Updated'))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

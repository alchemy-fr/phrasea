'use client';

import {useTranslation} from 'react-i18next';
import {AsyncCombobox} from '@/components/form/AsyncCombobox';
import {getProfiles, getPublications} from '@/lib/api/publications';
import {translateField} from '@/lib/utils/translate';
import type {PublicationRef} from '@/types/api';

/** Any publication but `excluded` (the edited one) can be the parent */
export function PublicationSelect({
    id,
    value,
    onChange,
    excluded,
    initial,
}: {
    id?: string;
    value: string | undefined;
    onChange: (value: string | undefined) => void;
    excluded?: string;
    /** The current parent, labelled before the list loads */
    initial?: PublicationRef | null;
}) {
    const {t, i18n} = useTranslation();
    const label = (p: PublicationRef) =>
        translateField(p, 'title', [i18n.language]) || p.slug || p.id;

    return (
        <AsyncCombobox
            id={id}
            queryKey={['publication-select', excluded]}
            value={value}
            onChange={v => onChange(v)}
            placeholder={t('publication.parent_none', 'No parent')}
            searchPlaceholder={t('common.search', 'Search…')}
            loadPage={async (query, _signal, cursor) => {
                const page = await getPublications(
                    {flatten: true, sort: 'name', query},
                    cursor
                );

                return {
                    options: page.items
                        .filter(p => p.id !== excluded)
                        .map(p => ({value: p.id, label: label(p)})),
                    next: page.next,
                };
            }}
            resolveValue={async v =>
                initial && initial.id === v
                    ? {value: v, label: label(initial)}
                    : undefined
            }
        />
    );
}

export function ProfileSelect({
    id,
    value,
    onChange,
    initialLabel,
}: {
    id?: string;
    value: string | undefined;
    onChange: (value: string | undefined) => void;
    initialLabel?: string;
}) {
    const {t} = useTranslation();

    return (
        <AsyncCombobox
            id={id}
            queryKey={['profile-select']}
            value={value}
            onChange={v => onChange(v)}
            placeholder={t('publication.profile_none', 'No profile')}
            searchPlaceholder={t('common.search', 'Search…')}
            loadPage={async (query, _signal, cursor) => {
                const page = await getProfiles(cursor, {query});

                return {
                    options: page.items.map(p => ({
                        value: p.id,
                        label: p.name,
                    })),
                    next: page.next,
                };
            }}
            resolveValue={async v =>
                initialLabel ? {value: v, label: initialLabel} : undefined
            }
        />
    );
}

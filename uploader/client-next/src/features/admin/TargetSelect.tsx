'use client';

import {useTranslation} from 'react-i18next';
import {AsyncCombobox} from '@/components/form/AsyncCombobox';
import {getTarget, getTargetsPage} from '@/lib/api/uploader';

/** Searchable select of the targets, loading them page by page */
export function TargetSelect({
    id,
    value,
    onChange,
    disabled,
}: {
    id?: string;
    value: string | undefined;
    onChange: (id: string | undefined) => void;
    disabled?: boolean;
}) {
    const {t} = useTranslation();

    return (
        <AsyncCombobox
            id={id}
            queryKey={['targets', 'select']}
            value={value}
            onChange={v => onChange(v)}
            disabled={disabled}
            placeholder={t('admin.target.placeholder', 'Select a target…')}
            loadPage={async (query, _signal, cursor) => {
                const page = await getTargetsPage(
                    {query: query || undefined},
                    cursor
                );

                return {
                    options: page.items.map(target => ({
                        value: target.id,
                        label: target.name,
                    })),
                    next: page.next,
                };
            }}
            resolveValue={async v => {
                const target = await getTarget(v);

                return {value: target.id, label: target.name};
            }}
        />
    );
}

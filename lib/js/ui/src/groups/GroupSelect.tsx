'use client';

import {useCallback} from 'react';
import {useTranslation} from 'react-i18next';
import {
    AsyncCombobox,
    type ComboOption,
    type ComboPage,
} from '../form/AsyncCombobox';
import {
    GROUPS_PAGE_SIZE,
    groupPickerQueryKey,
    type GroupsApi,
    type KeycloakGroup,
} from './types';

function groupOption(group: KeycloakGroup): ComboOption<KeycloakGroup> {
    return {value: group.id, label: group.name, item: group};
}

/**
 * Picks Keycloak groups by searching them; the value is their ids, whose
 * names are resolved through the API.
 */
export function GroupSelect({
    api,
    value,
    onChange,
    id,
    disabled,
    placeholder,
}: {
    api: Pick<GroupsApi, 'searchGroups' | 'getGroupsByIds'>;
    value: string[];
    onChange: (value: string[], groups: KeycloakGroup[]) => void;
    id?: string;
    disabled?: boolean;
    placeholder?: string;
}) {
    const {t} = useTranslation();
    // The cursor of a page is the offset of the next one, which may exist as
    // long as a page is full
    const loadPage = useCallback(
        async (
            query: string,
            signal: AbortSignal,
            cursor?: string
        ): Promise<ComboPage<KeycloakGroup>> => {
            const offset = Number(cursor ?? 0);
            const groups = await api.searchGroups(
                query,
                offset,
                GROUPS_PAGE_SIZE,
                signal
            );

            return {
                options: groups.map(groupOption),
                next:
                    groups.length === GROUPS_PAGE_SIZE
                        ? String(offset + GROUPS_PAGE_SIZE)
                        : undefined,
            };
        },
        [api]
    );
    const resolveValues = useCallback(
        async (ids: string[]) =>
            (await api.getGroupsByIds(ids)).map(groupOption),
        [api]
    );

    return (
        <AsyncCombobox<KeycloakGroup>
            id={id}
            multiple
            disabled={disabled}
            queryKey={[...groupPickerQueryKey]}
            loadPage={loadPage}
            resolveValues={resolveValues}
            value={value}
            onChange={(ids, options) =>
                onChange(
                    ids,
                    options
                        .map(o => o.item)
                        .filter((g): g is KeycloakGroup => !!g)
                )
            }
            placeholder={placeholder ?? t('groups.select', 'Select groups…')}
            searchPlaceholder={t('groups.search', 'Search groups…')}
            emptyLabel={t('groups.empty', 'No group')}
        />
    );
}

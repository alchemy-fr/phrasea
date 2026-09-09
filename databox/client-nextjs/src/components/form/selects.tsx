'use client';

import {useCallback} from 'react';
import {useTranslation} from 'react-i18next';
import {AsyncCombobox, ComboOption} from './AsyncCombobox';
import {
    getAttributeEntities,
    getTag,
    getTags,
    postAttributeEntity,
} from '@/lib/api/metadata';
import {getGroups, getUsers} from '@/lib/api/misc';
import {
    EntityName,
    type AttributeEntity,
    type Group,
    type Tag,
    type User,
} from '@/types/api';
import {TagChip, EntityChip} from '@/components/chips';
import {entityLabel} from '@/features/attributes/types/registry';
import {iri, idFromIri} from '@/lib/utils/iri';

function tagOption(tag: Tag, useIri: boolean): ComboOption<Tag> {
    return {
        value: useIri ? tag['@id'] : tag.id,
        label: tag.displayName ?? tag.name,
        render: <TagChip tag={tag} size="xs" />,
        item: tag,
    };
}

type TagSelectProps = {
    workspaceId?: string;
    /** store IRIs instead of ids */
    useIri?: boolean;
    disabled?: boolean;
    id?: string;
} & (
    | {
          multiple: true;
          value: string[];
          onChange: (value: string[], tags: Tag[]) => void;
      }
    | {
          multiple?: false;
          value: string | undefined;
          onChange: (value: string | undefined, tag?: Tag) => void;
      }
);

export function TagSelect(props: TagSelectProps) {
    const {t} = useTranslation();
    const {workspaceId, useIri = false, disabled, id} = props;
    const load = useCallback(
        async (query: string) => {
            const page = await getTags({
                workspace: workspaceId
                    ? iri(EntityName.Workspace, workspaceId)
                    : undefined,
                query: query || undefined,
            });

            return page.items.map(tg => tagOption(tg, useIri));
        },
        [workspaceId, useIri]
    );
    const resolve = useCallback(
        async (value: string) =>
            tagOption(await getTag(idFromIri(value)), useIri),
        [useIri]
    );

    if (props.multiple) {
        return (
            <AsyncCombobox<Tag>
                id={id}
                multiple
                disabled={disabled}
                queryKey={['tags', workspaceId]}
                loadOptions={load}
                resolveValue={resolve}
                value={props.value}
                onChange={(v, opts) =>
                    props.onChange(v, opts.map(o => o.item!).filter(Boolean))
                }
                placeholder={t('form.tags.placeholder', 'Select tags…')}
            />
        );
    }

    return (
        <AsyncCombobox<Tag>
            id={id}
            disabled={disabled}
            queryKey={['tags', workspaceId]}
            loadOptions={load}
            resolveValue={resolve}
            value={props.value}
            onChange={(v, opt) => props.onChange(v, opt?.item)}
            placeholder={t('form.tags.placeholder_single', 'Select a tag…')}
        />
    );
}

export function UserSelect({
    value,
    onChange,
    disabled,
    id,
    multiple,
}: {
    value: string | string[] | undefined;
    onChange: (value: any, users?: User[]) => void;
    disabled?: boolean;
    id?: string;
    multiple?: boolean;
}) {
    const {t} = useTranslation();
    const load = useCallback(async (query: string, signal: AbortSignal) => {
        const users = await getUsers(query || undefined, signal);

        return users.map(u => ({value: u.id, label: u.username, item: u}));
    }, []);

    if (multiple) {
        return (
            <AsyncCombobox<User>
                id={id}
                multiple
                disabled={disabled}
                queryKey={['users']}
                loadOptions={load}
                value={(value as string[]) ?? []}
                onChange={(v, opts) =>
                    onChange(v, opts.map(o => o.item!).filter(Boolean))
                }
                placeholder={t('form.users.placeholder', 'Select users…')}
            />
        );
    }

    return (
        <AsyncCombobox<User>
            id={id}
            disabled={disabled}
            queryKey={['users']}
            loadOptions={load}
            value={value as string | undefined}
            onChange={(v, opt) =>
                onChange(v, opt?.item ? [opt.item] : undefined)
            }
            placeholder={t('form.users.placeholder_single', 'Select a user…')}
        />
    );
}

export function GroupSelect({
    value,
    onChange,
    disabled,
    id,
    multiple,
}: {
    value: string | string[] | undefined;
    onChange: (value: any, groups?: Group[]) => void;
    disabled?: boolean;
    id?: string;
    multiple?: boolean;
}) {
    const {t} = useTranslation();
    const load = useCallback(async (query: string, signal: AbortSignal) => {
        const groups = await getGroups(query || undefined, signal);

        return groups.map(g => ({value: g.id, label: g.name, item: g}));
    }, []);

    if (multiple) {
        return (
            <AsyncCombobox<Group>
                id={id}
                multiple
                disabled={disabled}
                queryKey={['groups']}
                loadOptions={load}
                value={(value as string[]) ?? []}
                onChange={(v, opts) =>
                    onChange(v, opts.map(o => o.item!).filter(Boolean))
                }
                placeholder={t('form.groups.placeholder', 'Select groups…')}
            />
        );
    }

    return (
        <AsyncCombobox<Group>
            id={id}
            disabled={disabled}
            queryKey={['groups']}
            loadOptions={load}
            value={value as string | undefined}
            onChange={(v, opt) =>
                onChange(v, opt?.item ? [opt.item] : undefined)
            }
            placeholder={t('form.groups.placeholder_single', 'Select a group…')}
        />
    );
}

function entityOption(e: AttributeEntity): ComboOption<AttributeEntity> {
    return {
        value: e.id,
        label: entityLabel(e),
        render: <EntityChip entity={e} size="sm" />,
        item: e,
    };
}

export function AttributeEntitySelect({
    listId,
    allowNewValues,
    value,
    onChange,
    disabled,
    id,
}: {
    listId: string | undefined;
    allowNewValues?: boolean;
    value: string | undefined;
    onChange: (value: string | undefined, entity?: AttributeEntity) => void;
    disabled?: boolean;
    id?: string;
}) {
    const {t} = useTranslation();
    const load = useCallback(
        async (query: string) => {
            const page = await getAttributeEntities({
                list: listId,
                query: query || undefined,
            });

            return page.items.map(entityOption);
        },
        [listId]
    );

    return (
        <AsyncCombobox<AttributeEntity>
            id={id}
            disabled={disabled || !listId}
            queryKey={['attribute-entities', listId]}
            loadOptions={load}
            value={value}
            onChange={(v, opt) => onChange(v, opt?.item)}
            placeholder={t('form.entity.placeholder', 'Select a value…')}
            onCreate={
                allowNewValues && listId
                    ? async query =>
                          entityOption(
                              await postAttributeEntity(listId, {value: query})
                          )
                    : undefined
            }
        />
    );
}

'use client';

import {useCallback, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {AttributeEntity} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    getAttributeEntities,
    loadAttributeEntity,
    postAttributeEntity,
} from '@/lib/api/metadata';
import {
    AsyncCombobox,
    type ComboOption,
} from '@alchemy/next-ui/form/AsyncCombobox';
import {EntityChip} from '@/components/chips';
import {entityLabel} from '@/features/attributes/types/registry';
import type {FaceCardFace, FaceEntity} from './FaceCard';

function personOption(e: AttributeEntity): ComboOption<AttributeEntity> {
    return {
        value: e.id,
        label: entityLabel(e),
        render: <EntityChip entity={e} size="sm" />,
        item: e,
    };
}

export function isUnknownPerson(
    entity: FaceEntity | null | undefined
): boolean {
    return (
        !entity ||
        entity.status === AttributeEntityStatus.Pending ||
        !entity.value
    );
}

export type RunAction = <T>(action: () => Promise<T>) => Promise<T>;

/** A busy flag raised while the actions given to `run` are pending */
export function useRun(): [boolean, RunAction] {
    const [saving, setSaving] = useState(false);
    const run = useCallback(async <T,>(action: () => Promise<T>) => {
        setSaving(true);
        try {
            return await action();
        } finally {
            setSaving(false);
        }
    }, []);

    return [saving, run];
}

/**
 * The person of a face: an existing person of the list, or a typed name,
 * which names its unknown person (and so every face of that person) or else
 * creates a new person.
 */
export function FacePersonPicker<F extends FaceCardFace>({
    face,
    listId,
    disabled,
    saving,
    run,
    onAssign,
    onName,
    className,
}: {
    face: F;
    /** Entity list of the persons */
    listId: string | undefined;
    disabled?: boolean;
    saving: boolean;
    run: RunAction;
    /** Gives the face to a person (the entity ID, and its name when known) */
    onAssign: (face: F, entityId: string, name?: string) => Promise<void>;
    /**
     * Names the unknown person of the face (renamed and approved), and so
     * every face of that person. Without it, a new person is created.
     */
    onName?: (face: F, name: string) => Promise<AttributeEntity>;
    className?: string;
}) {
    const {t} = useTranslation();
    const unknown = isUnknownPerson(face.entity);
    const person = unknown ? undefined : face.entity?.id;

    // Only the named persons: a pending one is an unknown person, giving the
    // face to it would leave the face unnamed
    const loadPersons = useCallback(
        async (query: string) => {
            const page = await getAttributeEntities({
                list: listId,
                query: query || undefined,
                status: AttributeEntityStatus.Approved,
            });

            return page.items.map(personOption);
        },
        [listId]
    );
    const resolvePerson = useCallback(
        async (id: string) => personOption(await loadAttributeEntity(id)),
        []
    );

    return (
        <AsyncCombobox<AttributeEntity>
            queryKey={['attribute-entities', listId, 'approved']}
            loadOptions={loadPersons}
            resolveValue={resolvePerson}
            value={person}
            disabled={disabled || saving || !listId}
            className={className}
            placeholder={t('faces.group_name_placeholder', 'Who is this?')}
            searchPlaceholder={t(
                'faces.person_search_placeholder',
                'Search or type a name…'
            )}
            emptyLabel={t(
                'faces.person_empty',
                'No person named yet: type a name to name this face.'
            )}
            createLabel={query =>
                t('faces.group_create', {
                    defaultValue: 'New person « {{name}} »',
                    name: query,
                })
            }
            onCreate={
                listId
                    ? async query =>
                          personOption(
                              await run(() =>
                                  unknown && face.entity && onName
                                      ? onName(face, query)
                                      : postAttributeEntity(listId, {
                                            value: query,
                                            status: AttributeEntityStatus.Approved,
                                        })
                              )
                          )
                    : undefined
            }
            onChange={(value, option) => {
                // The renamed unknown person is already the face's one
                if (value && value !== face.entity?.id) {
                    void run(() => onAssign(face, value, option?.label));
                }
            }}
        />
    );
}

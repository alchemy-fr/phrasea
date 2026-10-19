'use client';

import {useCallback, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {UsersIcon} from 'lucide-react';
import type {AttributeEntity} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {getAttributeEntities, putAttributeEntity} from '@/lib/api/metadata';
import {AsyncCombobox} from '@/components/form/AsyncCombobox';
import {Badge} from '@/components/ui/misc';
import {FaceCard, personOption, type FaceCardFace} from './FaceCard';

/**
 * The faces of a person nobody has named yet (a pending entity): named at once
 * from the group header, as a new person (the entity is renamed and approved)
 * or as an existing one (the entity is merged into it).
 */
export function PersonGroup({
    person,
    faces,
    listId,
    canEdit,
    onNamed,
    onMerge,
    onAssign,
    onRemove,
}: {
    person: AttributeEntity;
    /** The loaded faces of the person (there may be more) */
    faces: FaceCardFace[];
    listId: string;
    canEdit: boolean;
    /** The pending person was renamed and approved */
    onNamed: (person: AttributeEntity) => Promise<void>;
    /** The pending person is merged into an existing one */
    onMerge: (person: AttributeEntity, targetId: string) => Promise<void>;
    onAssign: (
        face: FaceCardFace,
        entityId: string,
        name?: string
    ) => Promise<void>;
    onRemove: (face: FaceCardFace) => Promise<void>;
}) {
    const {t} = useTranslation();
    const [saving, setSaving] = useState(false);
    const count = person.usage?.attributes ?? faces.length;

    const load = useCallback(
        async (query: string) => {
            const page = await getAttributeEntities({
                list: listId,
                query: query || undefined,
                status: AttributeEntityStatus.Approved,
            });

            return page.items.filter(e => e.id !== person.id).map(personOption);
        },
        [listId, person.id]
    );

    return (
        <section
            className="rounded-lg border bg-muted/30"
            data-testid="person-group"
        >
            <div className="flex flex-wrap items-center gap-2 border-b px-3 py-2">
                <UsersIcon className="size-4 shrink-0 text-muted-foreground" />
                <span className="text-sm font-medium">
                    {t('faces.unknown_person', 'Unknown person')}
                </span>
                <Badge variant="muted" data-testid="person-group-count">
                    {t('faces.count', {
                        count,
                        defaultValue_one: '{{count}} face',
                        defaultValue_other: '{{count}} faces',
                    })}
                </Badge>
                <div className="flex-1" />
                {canEdit ? (
                    <div className="w-64" data-testid="person-group-name">
                        <AsyncCombobox<AttributeEntity>
                            queryKey={[
                                'attribute-entities',
                                listId,
                                'approved',
                            ]}
                            loadOptions={load}
                            value={undefined}
                            disabled={saving}
                            placeholder={t(
                                'faces.group_name_placeholder',
                                'Who is this?'
                            )}
                            createLabel={query =>
                                t('faces.group_create', {
                                    defaultValue: 'New person « {{name}} »',
                                    name: query,
                                })
                            }
                            onCreate={async query => {
                                setSaving(true);
                                try {
                                    const saved = await putAttributeEntity(
                                        person.id,
                                        {
                                            value: query,
                                            status: AttributeEntityStatus.Approved,
                                        }
                                    );
                                    await onNamed(saved);

                                    return personOption(saved);
                                } finally {
                                    setSaving(false);
                                }
                            }}
                            onChange={value => {
                                if (value && value !== person.id) {
                                    setSaving(true);
                                    void onMerge(person, value).finally(() =>
                                        setSaving(false)
                                    );
                                }
                            }}
                        />
                    </div>
                ) : null}
            </div>
            <div className="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3 p-3">
                {faces.map(face => (
                    <FaceCard
                        key={face.id}
                        face={face}
                        listId={listId}
                        canEdit={canEdit}
                        onAssign={onAssign}
                        onRemove={onRemove}
                    />
                ))}
            </div>
        </section>
    );
}

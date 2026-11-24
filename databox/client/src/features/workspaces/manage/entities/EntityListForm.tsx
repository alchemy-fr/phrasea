'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {SaveIcon} from 'lucide-react';
import {useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {EntityList} from '@/types/api';
import {
    getAttributeEntities,
    postEntityList,
    putEntityList,
} from '@/lib/api/metadata';
import {Button} from '@alchemy/next-ui/ui/button';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';
import {Checkbox, LabeledControl} from '@alchemy/next-ui/ui/controls';
import {useDirtyState} from '@alchemy/next-ui/navigation/unsavedChanges';
import {ConfirmDialog} from '@alchemy/next-ui/ui/confirm';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {AclEditor} from '@/features/permissions/AclEditor';
import {entityListPermissions} from '@/features/permissions/permissionDefinitions';
import {
    aclMasks,
    PermissionObject,
} from '@/features/permissions/permissionTypes';
import {hierarchyQueryKey} from './hierarchy/EntityTree';

/**
 * Properties of an entity list (name, options), created when `list` is
 * missing. With `withPermissions`, the users allowed to manage the list are
 * edited below, for those who may (`capabilities.editPermissions`).
 */
export function EntityListForm({
    list,
    workspaceId,
    onSaved,
    withPermissions,
}: {
    list?: EntityList;
    workspaceId: string;
    onSaved: (l: EntityList) => void;
    withPermissions?: boolean;
}) {
    const {t} = useTranslation();
    const [form, setForm] = useState({
        name: list?.name ?? '',
        allowNewValues: list?.allowNewValues ?? false,
        approveNewValues: list?.approveNewValues ?? false,
        withTranslations: list?.withTranslations ?? false,
        withSynonyms: list?.withSynonyms ?? false,
        withEmojis: list?.withEmojis ?? false,
        withColors: list?.withColors ?? false,
        withImages: list?.withImages ?? false,
        isHierarchy: list?.isHierarchy ?? false,
    });
    const [saving, setSaving] = useState(false);
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const {markSaved} = useDirtyState(form);
    const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) =>
        setForm(f => ({...f, [k]: v}));

    /** Whether the list has entities below the top level or empty levels */
    const hasHierarchy = async (listId: string): Promise<boolean> => {
        const [nested, placeholders] = await Promise.all([
            getAttributeEntities({list: listId, depth: 1, limit: 1}),
            getAttributeEntities({list: listId, placeholder: true, limit: 1}),
        ]);

        return nested.total > 0 || placeholders.total > 0;
    };

    const submit = async () => {
        if (list?.isHierarchy && !form.isHierarchy) {
            setSaving(true);
            let flatten = false;
            try {
                flatten = await hasHierarchy(list.id);
            } catch (e: any) {
                toast.error(e?.message);
                setSaving(false);

                return;
            }
            setSaving(false);
            if (flatten) {
                openModal(ConfirmDialog, {
                    title: t(
                        'entity_list.flatten_title',
                        'The hierarchy will be deleted'
                    ),
                    description: t(
                        'entity_list.flatten_confirm',
                        'Every value goes back to the top level, the empty levels are removed, the attribute definitions are no longer bound to hierarchy levels and the values filled by the hierarchy are removed from the assets.'
                    ),
                    confirmLabel: t(
                        'entity_list.flatten',
                        'Delete the hierarchy'
                    ),
                    destructive: true,
                    onConfirm: save,
                });

                return;
            }
        }
        await save();
    };

    const save = async () => {
        setSaving(true);
        try {
            const saved = list
                ? await putEntityList(list.id, form)
                : await postEntityList(workspaceId, form);
            toast.success(t('entity_list.saved', 'List saved'));
            queryClient.setQueryData(['entity-list', saved.id], saved);
            if (!!list?.isHierarchy !== !!saved.isHierarchy) {
                // Flattened, or flagged again: the cached tree is obsolete
                queryClient.removeQueries({
                    queryKey: hierarchyQueryKey(saved.id),
                });
                queryClient.invalidateQueries({
                    queryKey: ['attribute-entities'],
                });
            }
            markSaved();
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    type Flag = [keyof typeof form, string, string];
    const groups: {title: string; flags: Flag[]}[] = [
        {
            title: t('entity_list.group.new_values', 'New values'),
            flags: [
                [
                    'allowNewValues',
                    t(
                        'entity_list.allow_new',
                        'Accept new values from attribute fields'
                    ),
                    t(
                        'entity_list.allow_new_help',
                        'Users editing the assets can add a value missing from the list.'
                    ),
                ],
                [
                    'approveNewValues',
                    t(
                        'entity_list.approve_new',
                        'New values are approved automatically'
                    ),
                    t(
                        'entity_list.approve_new_help',
                        'Otherwise, values added by users who cannot manage the list wait for an approval.'
                    ),
                ],
            ],
        },
        {
            title: t('entity_list.group.language', 'Language'),
            flags: [
                [
                    'withTranslations',
                    t('entity_list.with_translations', 'Translations'),
                    t(
                        'entity_list.with_translations_help',
                        'Each value can be translated into the workspace languages.'
                    ),
                ],
                [
                    'withSynonyms',
                    t('entity_list.with_synonyms', 'Synonyms'),
                    t(
                        'entity_list.with_synonyms_help',
                        'Other terms finding the value in searches.'
                    ),
                ],
            ],
        },
        {
            title: t('entity_list.group.appearance', 'Appearance'),
            flags: [
                [
                    'withEmojis',
                    t('entity_list.with_emojis', 'Emojis'),
                    t(
                        'entity_list.with_emojis_help',
                        'An emoji shown next to the value.'
                    ),
                ],
                [
                    'withColors',
                    t('entity_list.with_colors', 'Colors'),
                    t(
                        'entity_list.with_colors_help',
                        'A color shown next to the value.'
                    ),
                ],
                [
                    'withImages',
                    t('entity_list.with_images', 'Photos'),
                    t(
                        'entity_list.with_images_help',
                        'A photo illustrating the value.'
                    ),
                ],
            ],
        },
        {
            title: t('entity_list.group.structure', 'Structure'),
            flags: [
                [
                    'isHierarchy',
                    t('entity_list.is_hierarchy', 'Hierarchy (tree of values)'),
                    t(
                        'entity_list.is_hierarchy_help',
                        'The values are organized as a tree, each one possibly under a parent value.'
                    ),
                ],
            ],
        },
    ];

    return (
        <div className="space-y-4">
            <FormRow label={t('common.name', 'Name')}>
                <Input
                    value={form.name}
                    onChange={e => set('name', e.target.value)}
                />
            </FormRow>
            {groups.map(g => (
                <fieldset key={g.title} className="grid gap-3">
                    <legend className="mb-2 text-sm font-semibold">
                        {g.title}
                    </legend>
                    {g.flags.map(([k, label, description]) => (
                        <LabeledControl
                            key={k}
                            label={label}
                            description={description}
                        >
                            <Checkbox
                                checked={!!form[k]}
                                onCheckedChange={v =>
                                    set(k, (v === true) as never)
                                }
                            />
                        </LabeledControl>
                    ))}
                </fieldset>
            ))}
            <div className="flex justify-end">
                <Button
                    onClick={submit}
                    loading={saving}
                    disabled={!form.name.trim()}
                >
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
            {withPermissions && list?.capabilities?.editPermissions ? (
                <div
                    className="border-t pt-4"
                    data-testid="entity-list-permissions"
                >
                    <h4 className="mb-2 text-sm font-semibold">
                        {t('collection.manage.permissions', 'Permissions')}
                    </h4>
                    <AclEditor
                        objectType={PermissionObject.EntityList}
                        objectId={list.id}
                        definitions={entityListPermissions(t)}
                        defaultMask={aclMasks.EDIT}
                    />
                </div>
            ) : null}
        </div>
    );
}

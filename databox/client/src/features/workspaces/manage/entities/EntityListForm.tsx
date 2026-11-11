'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {EntityList} from '@/types/api';
import {postEntityList, putEntityList} from '@/lib/api/metadata';
import {Button} from '@alchemy/next-ui/ui/button';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';
import {Checkbox, LabeledControl} from '@alchemy/next-ui/ui/controls';
import {useDirtyState} from '@alchemy/next-ui/navigation/unsavedChanges';
import {AclEditor} from '@/features/permissions/AclEditor';
import {entityListPermissions} from '@/features/permissions/permissionDefinitions';
import {
    aclMasks,
    PermissionObject,
} from '@/features/permissions/permissionTypes';

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
    });
    const [saving, setSaving] = useState(false);
    const {markSaved} = useDirtyState(form);
    const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) =>
        setForm(f => ({...f, [k]: v}));

    const save = async () => {
        setSaving(true);
        try {
            const saved = list
                ? await putEntityList(list.id, form)
                : await postEntityList(workspaceId, form);
            toast.success(t('entity_list.saved', 'List saved'));
            markSaved();
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    const flags: [keyof typeof form, string][] = [
        [
            'allowNewValues',
            t(
                'entity_list.allow_new',
                'Accept new values from attribute fields'
            ),
        ],
        [
            'approveNewValues',
            t(
                'entity_list.approve_new',
                'New values are approved automatically'
            ),
        ],
        [
            'withTranslations',
            t('entity_list.with_translations', 'Translations'),
        ],
        ['withSynonyms', t('entity_list.with_synonyms', 'Synonyms')],
        ['withEmojis', t('entity_list.with_emojis', 'Emojis')],
        ['withColors', t('entity_list.with_colors', 'Colors')],
        ['withImages', t('entity_list.with_images', 'Photos')],
    ];

    return (
        <div className="space-y-4">
            <FormRow label={t('common.name', 'Name')}>
                <Input
                    value={form.name}
                    onChange={e => set('name', e.target.value)}
                />
            </FormRow>
            <div className="grid gap-2 sm:grid-cols-2">
                {flags.map(([k, label]) => (
                    <LabeledControl key={k} label={label}>
                        <Checkbox
                            checked={!!form[k]}
                            onCheckedChange={v => set(k, (v === true) as never)}
                        />
                    </LabeledControl>
                ))}
            </div>
            <div className="flex justify-end">
                <Button
                    onClick={save}
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

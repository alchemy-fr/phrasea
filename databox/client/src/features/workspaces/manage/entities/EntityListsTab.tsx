'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {EntityList} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {
    deleteEntityList,
    getEntityLists,
    postEntityList,
    putEntityList,
} from '@/lib/api/metadata';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {Badge} from '@/components/ui/misc';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';
import {ExploreEntityListButton} from '@/features/entities/explore/ExploreEntityListButton';
import {EntityGrid} from './EntityGrid';

export function EntityListsTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const lists = useQuery({
        queryKey: ['entity-lists', workspace.id],
        queryFn: () => getEntityLists({workspaceId: workspace.id}),
    });

    return (
        <DefinitionManager<EntityList>
            items={lists.data?.items}
            loading={lists.isLoading}
            onChanged={() => lists.refetch()}
            filter={(l, q) => l.name.toLowerCase().includes(q)}
            renderItem={l => (
                <span className="flex items-center gap-2">
                    <span className="flex-1 truncate">{l.name}</span>
                    {l.allowNewValues ? (
                        <Badge variant="muted">
                            {t('entity_list.open', 'open')}
                        </Badge>
                    ) : null}
                </span>
            )}
            onDelete={l => deleteEntityList(l.id)}
            createLabel={t('entity_list.create', 'New list')}
            manageLabel={t('entity_list.manage', 'Values')}
            renderRowActions={l => (
                <ExploreEntityListButton
                    listId={l.id}
                    className="h-7 text-xs"
                />
            )}
            renderManage={(l, back) => (
                <EntityGrid list={l} workspace={workspace} onBack={back} />
            )}
            renderForm={(l, onSaved) => (
                <ListForm
                    key={l?.id ?? 'new'}
                    list={l}
                    workspaceId={workspace.id}
                    onSaved={onSaved}
                />
            )}
        />
    );
}

function ListForm({
    list,
    workspaceId,
    onSaved,
}: {
    list?: EntityList;
    workspaceId: string;
    onSaved: (l: EntityList) => void;
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
        </div>
    );
}

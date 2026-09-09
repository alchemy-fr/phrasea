'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Tag} from '@/types/api';
import {EntityName} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {deleteTag, getTags, postTag, putTag} from '@/lib/api/metadata';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {TranslatableField} from '@/components/form/TranslatableField';
import {TagChip} from '@/components/chips';
import {iri} from '@/lib/utils/iri';

export function TagManagerTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const tags = useQuery({
        queryKey: ['tags', 'manage', workspace.id],
        queryFn: () =>
            getTags({workspace: iri(EntityName.Workspace, workspace.id)}),
    });

    return (
        <DefinitionManager<Tag>
            items={tags.data?.items}
            loading={tags.isLoading}
            onChanged={() => tags.refetch()}
            filter={(tag, q) =>
                (tag.displayName ?? tag.name).toLowerCase().includes(q)
            }
            renderItem={tag => <TagChip tag={tag} size="sm" />}
            onDelete={tag => deleteTag(tag.id)}
            createLabel={t('tag.create', 'New tag')}
            renderForm={(tag, onSaved) => (
                <TagForm
                    key={tag?.id ?? 'new'}
                    tag={tag}
                    workspaceId={workspace.id}
                    locales={workspace.enabledLocales ?? []}
                    onSaved={onSaved}
                />
            )}
        />
    );
}

function TagForm({
    tag,
    workspaceId,
    locales,
    onSaved,
}: {
    tag?: Tag;
    workspaceId: string;
    locales: string[];
    onSaved: (tag: Tag) => void;
}) {
    const {t} = useTranslation();
    const [name, setName] = useState(tag?.name ?? '');
    const [translations, setTranslations] = useState<Record<string, string>>(
        tag?.translations?.name ?? {}
    );
    const [color, setColor] = useState(tag?.color ?? '');
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const data = {
                name,
                color: color || null,
                translations: {name: translations},
            } as Partial<Tag>;
            const saved = tag
                ? await putTag(tag.id, data)
                : await postTag({
                      ...data,
                      workspace: iri(EntityName.Workspace, workspaceId),
                  });
            toast.success(t('tag.saved', 'Tag saved'));
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-3">
            <TranslatableField
                label={t('common.name', 'Name')}
                value={name}
                onChange={setName}
                translations={translations}
                onTranslationsChange={setTranslations}
                locales={locales}
            />
            <FormRow label={t('tag.color', 'Color')}>
                <div className="flex items-center gap-2">
                    <input
                        type="color"
                        value={
                            /^#[0-9a-f]{6}$/i.test(color) ? color : '#888888'
                        }
                        onChange={e => setColor(e.target.value)}
                        className="size-9 cursor-pointer rounded border bg-transparent"
                    />
                    <Input
                        value={color}
                        onChange={e => setColor(e.target.value)}
                        placeholder="#rrggbb"
                        className="w-32 font-mono"
                    />
                    {color ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setColor('')}
                        >
                            {t('common.clear', 'Clear')}
                        </Button>
                    ) : null}
                </div>
            </FormRow>
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

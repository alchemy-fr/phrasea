'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import {AssetTypeFilter, EntityName, Privacy} from '@/types/api';
import type {AssetTabProps} from '../AssetManageRoute';
import {Button} from '@/components/ui/button';
import {FormRow} from '@/components/ui/input';
import {TagSelect} from '@/components/form/selects';
import {PrivacyField} from '@/components/form/PrivacyField';
import {AttributesEditor} from '@/features/attributes/editor/AttributesEditor';
import {useAttributeEditor} from '@/features/attributes/editor/useAttributeEditor';
import {attributeBatchUpdate, patchAsset} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';
import {iri} from '@/lib/utils/iri';
import {InlineLoader} from '@/components/ui/loader';
import {useUnsavedChangesPrompt} from '@/hooks/useUnsavedChangesPrompt';

export function AssetEditTab({asset, refresh}: AssetTabProps) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const update = useAssetStore(s => s.update);
    const [tags, setTags] = useState<string[]>(
        (asset.tags ?? []).map(tg => tg.id)
    );
    const [privacy, setPrivacy] = useState<Privacy | undefined>(asset.privacy);
    const [saving, setSaving] = useState(false);
    const editor = useAttributeEditor({
        workspaceId: asset.workspace.id,
        assetId: asset.id,
        target: asset.storyCollection
            ? AssetTypeFilter.Story
            : AssetTypeFilter.Asset,
    });

    const tagsDirty =
        JSON.stringify(tags) !==
        JSON.stringify((asset.tags ?? []).map(tg => tg.id));
    const privacyDirty = privacy !== asset.privacy;
    const dirty = editor.dirty || tagsDirty || privacyDirty;
    useUnsavedChangesPrompt(dirty);

    useEffect(() => {
        setTags((asset.tags ?? []).map(tg => tg.id));
        setPrivacy(asset.privacy);
    }, [asset]);

    const save = async () => {
        setSaving(true);
        try {
            if (tagsDirty || privacyDirty) {
                const updated = await patchAsset(asset.id, {
                    tags: tagsDirty
                        ? tags.map(id => iri(EntityName.Tag, id))
                        : undefined,
                    privacy: privacyDirty ? privacy : undefined,
                });
                update(updated);
            }
            const actions = editor.getActions();
            if (actions.length > 0) {
                const updated = await attributeBatchUpdate(asset.id, actions);
                update(updated);
                editor.applyRemote(updated.attributes);
                void queryClient.invalidateQueries({
                    queryKey: ['asset-attributes', asset.id],
                });
            }
            void queryClient.invalidateQueries({
                queryKey: ['asset-view', asset.id],
            });
            refresh();
            toast.success(t('asset.edit.saved', 'Asset saved'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="max-w-3xl space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
                <FormRow label={t('common.tags', 'Tags')}>
                    <TagSelect
                        multiple
                        workspaceId={asset.workspace.id}
                        value={tags}
                        onChange={setTags}
                        disabled={!asset.capabilities.edit}
                    />
                </FormRow>
                <PrivacyField
                    value={privacy}
                    onChange={setPrivacy}
                    disabled={
                        !asset.capabilities.editPermissions &&
                        !asset.capabilities.edit
                    }
                />
            </div>
            {editor.loading ? (
                <InlineLoader />
            ) : (
                <AttributesEditor
                    attributes={editor.attributes}
                    definitions={editor.definitions}
                    onChange={editor.onChange}
                    disabled={!asset.capabilities.editAttributes}
                    target={
                        asset.storyCollection
                            ? AssetTypeFilter.Story
                            : AssetTypeFilter.Asset
                    }
                    workspaceId={asset.workspace.id}
                    workspaceLocales={asset.workspace.enabledLocales}
                />
            )}
            <div className="sticky bottom-0 flex justify-end gap-2 border-t bg-background py-3">
                <Button
                    variant="ghost"
                    disabled={!dirty || saving}
                    onClick={() => {
                        editor.reset();
                        setTags((asset.tags ?? []).map(tg => tg.id));
                        setPrivacy(asset.privacy);
                    }}
                >
                    {t('common.reset', 'Reset')}
                </Button>
                <Button onClick={save} disabled={!dirty} loading={saving}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

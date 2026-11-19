'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import {AssetTypeFilter, Privacy} from '@/types/api';
import type {AssetTabProps} from '../types';
import {Button} from '@alchemy/next-ui/ui/button';
import {PrivacyField} from '@/components/form/PrivacyField';
import {AttributesEditor} from '@/features/attributes/editor/AttributesEditor';
import {useAttributeEditor} from '@/features/attributes/editor/useAttributeEditor';
import {attributeBatchUpdate, patchAsset} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {useUnsavedChangesPrompt} from '@alchemy/next-ui/navigation/unsavedChanges';
import {AssetEvent, EventCategory, track} from '@/lib/analytics/events';

export function AssetEditTab({asset, refresh}: AssetTabProps) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const update = useAssetStore(s => s.update);
    const [privacy, setPrivacy] = useState<Privacy | undefined>(asset.privacy);
    const [saving, setSaving] = useState(false);
    const editor = useAttributeEditor({
        workspaceId: asset.workspace.id,
        assetId: asset.id,
        target: asset.storyCollection
            ? AssetTypeFilter.Story
            : AssetTypeFilter.Asset,
    });

    const privacyDirty = privacy !== asset.privacy;
    const dirty = editor.dirty || privacyDirty;
    useUnsavedChangesPrompt(dirty);

    useEffect(() => {
        setPrivacy(asset.privacy);
    }, [asset]);

    const save = async () => {
        setSaving(true);
        try {
            if (privacyDirty) {
                const updated = await patchAsset(asset.id, {privacy});
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
            track(EventCategory.Asset, AssetEvent.Edit, {value: 1});
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
                    hasMore={editor.hasMore}
                    onLoadMore={editor.loadMore}
                    loadingMore={editor.loadingMore}
                    onChange={editor.onChange}
                    disabled={!asset.capabilities.editAttributes}
                    target={
                        asset.storyCollection
                            ? AssetTypeFilter.Story
                            : AssetTypeFilter.Asset
                    }
                    workspaceLocales={asset.workspace.enabledLocales}
                />
            )}
            <div className="sticky bottom-0 flex justify-end gap-2 border-t bg-background py-3">
                <Button
                    variant="ghost"
                    disabled={!dirty || saving}
                    onClick={() => {
                        editor.reset();
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

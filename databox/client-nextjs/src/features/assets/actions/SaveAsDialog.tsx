'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {ApiFile, Asset} from '@/types/api';
import {AssetType} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {FormRow, Input} from '@/components/ui/input';
import {RadioGroup, RadioGroupItem} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {
    CollectionTreePicker,
    TreeSelection,
} from '@/components/form/CollectionTreePicker';
import {getRenditionDefinitions, postRendition} from '@/lib/api/misc';
import {destinationToAssetProps, patchAsset, postAsset} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';

type Mode = 'new_asset' | 'rendition' | 'replace_source';

/**
 * "Save as" a stored file (source, version or rendition): create a new asset,
 * use it as a rendition of the current asset, or replace the source file.
 */
export function SaveAsDialog({
    open,
    onOpenChange,
    resolve,
    asset,
    file,
    onComplete,
}: ModalProps<Mode> & {
    asset: Asset;
    file: ApiFile;
    onComplete?: () => void;
}) {
    const {t} = useTranslation();
    const [mode, setMode] = useState<Mode>('new_asset');
    const [name, setName] = useState(`${asset.name ?? ''} - copy`);
    const [destination, setDestination] = useState<TreeSelection>();
    const [definitionId, setDefinitionId] = useState<string>();
    const update = useAssetStore(s => s.update);

    const definitions = useQuery({
        queryKey: ['rendition-definitions', asset.workspace.id, asset.id],
        queryFn: () =>
            getRenditionDefinitions({
                workspaceIds: [asset.workspace.id],
                assetId: asset.id,
                target: asset.storyCollection
                    ? AssetType.Story
                    : AssetType.Asset,
            }),
        enabled: mode === 'rendition',
    });

    const submit = async () => {
        if (mode === 'new_asset') {
            await postAsset({
                name,
                ...destinationToAssetProps(destination!.iri),
                sourceFileId: file.id,
                relationship: {
                    source: asset.id,
                    type: 'save_as',
                    sourceFile: file.id,
                },
            });
            toast.success(t('asset.save_as.created', 'New asset created'));
        } else if (mode === 'rendition') {
            await postRendition({
                assetId: asset.id,
                definitionId: definitionId!,
                sourceFileId: file.id,
                substituted: true,
                force: true,
            });
            toast.success(t('asset.save_as.rendition_done', 'Rendition saved'));
        } else {
            const updated = await patchAsset(asset.id, {
                sourceFileId: file.id,
            });
            update(updated);
            toast.success(t('asset.replace.done', 'Source file replaced'));
        }
        onComplete?.();
        resolve?.(mode);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('asset.save_as.title', 'Save file as…')}
            submitLabel={t('common.save', 'Save')}
            canSubmit={
                (mode !== 'new_asset' || !!destination) &&
                (mode !== 'rendition' || !!definitionId)
            }
            bodyClassName="space-y-4"
            onSubmit={submit}
        >
            <RadioGroup
                value={mode}
                onValueChange={v => setMode(v as Mode)}
                className="space-y-2"
            >
                <label className="flex items-center gap-2 text-sm">
                    <RadioGroupItem value="new_asset" />{' '}
                    {t('asset.save_as.new_asset', 'New asset')}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <RadioGroupItem value="rendition" />{' '}
                    {t('asset.save_as.rendition', 'Rendition of this asset')}
                </label>
                {asset.capabilities.edit ? (
                    <label className="flex items-center gap-2 text-sm">
                        <RadioGroupItem value="replace_source" />{' '}
                        {t(
                            'asset.save_as.replace_source',
                            'Replace source file of this asset'
                        )}
                    </label>
                ) : null}
            </RadioGroup>
            {mode === 'new_asset' ? (
                <>
                    <FormRow label={t('common.name', 'Name')}>
                        <Input
                            value={name}
                            onChange={e => setName(e.target.value)}
                        />
                    </FormRow>
                    <CollectionTreePicker
                        value={destination}
                        onChange={setDestination}
                        requireCapability="createAsset"
                        allowCreate
                    />
                </>
            ) : null}
            {mode === 'rendition' ? (
                <FormRow
                    label={t(
                        'asset.save_as.rendition_definition',
                        'Rendition definition'
                    )}
                >
                    <SimpleSelect
                        value={definitionId}
                        onValueChange={setDefinitionId}
                        placeholder={t('common.select', 'Select…')}
                        options={(definitions.data?.items ?? [])
                            .filter(d => d.substitutable)
                            .map(d => ({
                                value: d.id,
                                label: d.displayName ?? d.name,
                            }))}
                    />
                </FormRow>
            ) : null}
        </FormDialog>
    );
}

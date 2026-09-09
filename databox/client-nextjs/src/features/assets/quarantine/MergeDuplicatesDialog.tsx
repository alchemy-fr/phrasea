'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {Asset, DuplicateAsset} from '@/types/api';
import {AttributeBatchActionEnum} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {SimpleSelect} from '@/components/ui/select';
import {RadioGroup, RadioGroupItem} from '@/components/ui/controls';
import {
    addAsAssetVersion,
    attributeBatchUpdate,
    deleteAssets,
} from '@/lib/api/assets';
import {groupAttributes} from '@/features/attributes/AttributeValue';
import {AttributeValue} from '@/features/attributes/AttributeValue';
import {AssetThumb} from '@/features/assets/list/AssetThumb';

/**
 * Merge a quarantined asset into an existing duplicate: pick, attribute by
 * attribute, which value to keep, then either add the incoming file as a new
 * version of the target or drop it.
 */
export function MergeDuplicatesDialog({
    open,
    onOpenChange,
    asset,
    duplicates,
    onComplete,
}: ModalProps & {
    asset: Asset;
    duplicates: DuplicateAsset[];
    onComplete?: () => void;
}) {
    const {t} = useTranslation();
    const [targetId, setTargetId] = useState(duplicates[0]?.asset.id);
    const [choices, setChoices] = useState<
        Record<string, 'incoming' | 'existing'>
    >({});
    const [addVersion, setAddVersion] = useState(true);
    const [loading, setLoading] = useState(false);
    const target = duplicates.find(d => d.asset.id === targetId)?.asset;

    const rows = useMemo(() => {
        if (!target) {
            return [];
        }
        const incoming = groupAttributes(asset.attributes);
        const existing = groupAttributes(target.attributes);
        const defIds = new Set([
            ...incoming.map(g => g.definition.id),
            ...existing.map(g => g.definition.id),
        ]);

        return [...defIds].map(id => ({
            id,
            definition: (incoming.find(g => g.definition.id === id) ??
                existing.find(g => g.definition.id === id))!.definition,
            incoming: incoming.find(g => g.definition.id === id)?.attribute,
            existing: existing.find(g => g.definition.id === id)?.attribute,
        }));
    }, [asset, target]);

    const submit = async () => {
        if (!target) {
            return;
        }
        setLoading(true);
        try {
            const actions = rows
                .filter(r => choices[r.id] === 'incoming' && r.incoming)
                .map(r => {
                    const list = Array.isArray(r.incoming)
                        ? r.incoming
                        : [r.incoming!];

                    return {
                        action: AttributeBatchActionEnum.Set,
                        definitionId: r.id,
                        value: r.definition.multiple
                            ? list.map(a => a.value)
                            : list[0].value,
                        locale: list[0].locale,
                    };
                });
            if (actions.length > 0) {
                await attributeBatchUpdate(target.id, actions);
            }
            if (addVersion) {
                await addAsAssetVersion(asset.id, target.id);
            } else {
                await deleteAssets([asset.id], {hardDelete: true});
            }
            toast.success(t('quarantine.merged', 'Duplicates merged'));
            onComplete?.();
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="lg">
                <DialogHeader>
                    <DialogTitle>
                        {t('quarantine.merge', 'Merge duplicates')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'quarantine.merge_help',
                            'Choose, for each attribute, the value to keep on the existing asset.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody className="space-y-4">
                    {duplicates.length > 1 ? (
                        <SimpleSelect
                            value={targetId}
                            onValueChange={setTargetId}
                            options={duplicates.map(d => ({
                                value: d.asset.id,
                                label: d.asset.name ?? d.asset.id,
                            }))}
                        />
                    ) : null}
                    {target ? (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-muted-foreground uppercase">
                                    <th className="w-1/4 py-1">
                                        {t('common.attribute', 'Attribute')}
                                    </th>
                                    <th className="py-1">
                                        <div className="flex items-center gap-2">
                                            <span className="size-10 overflow-hidden rounded bg-media-bg">
                                                <AssetThumb
                                                    asset={asset}
                                                    size={40}
                                                />
                                            </span>
                                            {t(
                                                'quarantine.incoming',
                                                'Incoming file'
                                            )}
                                        </div>
                                    </th>
                                    <th className="py-1">
                                        <div className="flex items-center gap-2">
                                            <span className="size-10 overflow-hidden rounded bg-media-bg">
                                                <AssetThumb
                                                    asset={target}
                                                    size={40}
                                                />
                                            </span>
                                            {t(
                                                'quarantine.existing',
                                                'Existing asset'
                                            )}
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map(r => (
                                    <tr key={r.id} className="border-t">
                                        <td className="py-2 pr-2 font-medium">
                                            {r.definition.displayName ??
                                                r.definition.name}
                                        </td>
                                        {(
                                            ['incoming', 'existing'] as const
                                        ).map(side => (
                                            <td
                                                key={side}
                                                className="py-2 pr-2"
                                            >
                                                <label className="flex cursor-pointer items-start gap-2">
                                                    <RadioGroup
                                                        value={
                                                            choices[r.id] ??
                                                            'existing'
                                                        }
                                                        onValueChange={v =>
                                                            setChoices(c => ({
                                                                ...c,
                                                                [r.id]: v as
                                                                    | 'incoming'
                                                                    | 'existing',
                                                            }))
                                                        }
                                                    >
                                                        <RadioGroupItem
                                                            value={side}
                                                            className="mt-1"
                                                        />
                                                    </RadioGroup>
                                                    <span className="min-w-0 flex-1">
                                                        <AttributeValue
                                                            definition={
                                                                r.definition
                                                            }
                                                            attribute={r[side]}
                                                        />
                                                    </span>
                                                </label>
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : null}
                    <RadioGroup
                        value={addVersion ? 'version' : 'delete'}
                        onValueChange={v => setAddVersion(v === 'version')}
                        className="space-y-2"
                    >
                        <label className="flex items-center gap-2 text-sm">
                            <RadioGroupItem value="version" />{' '}
                            {t(
                                'quarantine.add_as_version',
                                'Keep the incoming file as a new version of the existing asset'
                            )}
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <RadioGroupItem value="delete" />{' '}
                            {t(
                                'quarantine.discard_incoming',
                                'Discard the incoming file'
                            )}
                        </label>
                    </RadioGroup>
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        loading={loading}
                        disabled={!target}
                    >
                        {t('quarantine.merge', 'Merge duplicates')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

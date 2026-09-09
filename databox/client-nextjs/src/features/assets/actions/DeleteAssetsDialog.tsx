'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {AlertTriangleIcon, Trash2Icon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
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
import {Input} from '@/components/ui/input';
import {
    Checkbox,
    LabeledControl,
    RadioGroup,
    RadioGroupItem,
} from '@/components/ui/controls';
import {Alert} from '@/components/ui/misc';
import {InlineLoader} from '@/components/ui/loader';
import {CollectionChip} from '@/components/chips';
import {deleteAssets, prepareDeleteAssets} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';

type Props = ModalProps & {
    assets: Asset[];
    hardDelete?: boolean;
    onComplete?: () => void;
};

/**
 * Delete confirmation: move to trash, or only remove the assets from some of
 * their collections (shortcuts). Permanent deletion requires typing "Delete".
 */
export function DeleteAssetsDialog({
    open,
    onOpenChange,
    assets,
    hardDelete,
    onComplete,
}: Props) {
    const {t} = useTranslation();
    const ids = assets.map(a => a.id);
    const [mode, setMode] = useState<'trash' | 'collections'>('trash');
    const [selectedCollections, setSelectedCollections] = useState<string[]>(
        []
    );
    const [typed, setTyped] = useState('');
    const [loading, setLoading] = useState(false);
    const removeFromStore = useAssetStore(s => s.remove);

    const prepare = useQuery({
        queryKey: ['assets', 'prepare-delete', ids],
        queryFn: () => prepareDeleteAssets(ids),
        staleTime: 2000,
    });

    const confirmWord = t('asset.delete.confirm_word', 'Delete');
    const needsTyping = !!hardDelete;
    const collections = prepare.data?.collections ?? [];
    const disabled =
        loading ||
        (needsTyping && typed.trim() !== confirmWord) ||
        (mode === 'collections' && selectedCollections.length === 0);

    const submit = async () => {
        setLoading(true);
        try {
            await deleteAssets(ids, {
                collections: mode === 'collections' ? selectedCollections : [],
                hardDelete: mode === 'trash' && hardDelete,
            });
            if (mode === 'trash') {
                removeFromStore(ids);
            }
            toast.success(
                hardDelete && mode === 'trash'
                    ? t(
                          'asset.delete.done_permanent',
                          '{{count}} asset(s) permanently deleted',
                          {count: ids.length}
                      )
                    : t(
                          'asset.delete.done',
                          '{{count}} asset(s) moved to trash',
                          {count: ids.length}
                      )
            );
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
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {hardDelete
                            ? t(
                                  'asset.delete.title_permanent',
                                  'Permanently delete {{count}} asset(s)?',
                                  {count: ids.length}
                              )
                            : t(
                                  'asset.delete.title',
                                  'Delete {{count}} asset(s)?',
                                  {count: ids.length}
                              )}
                    </DialogTitle>
                    {!hardDelete ? (
                        <DialogDescription>
                            {t(
                                'asset.delete.description',
                                'Assets moved to the trash can be restored later.'
                            )}
                        </DialogDescription>
                    ) : null}
                </DialogHeader>
                <DialogBody className="space-y-3">
                    {prepare.isLoading ? <InlineLoader /> : null}
                    {prepare.data &&
                    !prepare.data.canDelete &&
                    collections.length === 0 ? (
                        <Alert
                            variant="destructive"
                            icon={<AlertTriangleIcon />}
                        >
                            {t(
                                'asset.delete.not_allowed',
                                "You don't have permission to delete any of these assets"
                            )}
                        </Alert>
                    ) : null}
                    {prepare.data && prepare.data.shareCount > 0 ? (
                        <Alert variant="warning" icon={<AlertTriangleIcon />}>
                            {t(
                                'asset.delete.shared_warning',
                                '{{count}} asset(s) are currently shared with a public link.',
                                {count: prepare.data.shareCount}
                            )}
                        </Alert>
                    ) : null}
                    {collections.length > 0 && !hardDelete ? (
                        <RadioGroup
                            value={mode}
                            onValueChange={v =>
                                setMode(v as 'trash' | 'collections')
                            }
                            className="space-y-2"
                        >
                            <label className="flex items-center gap-2 text-sm">
                                <RadioGroupItem value="trash" />{' '}
                                {t('asset.delete.mode_trash', 'Move to trash')}
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <RadioGroupItem value="collections" />{' '}
                                {t(
                                    'asset.delete.mode_collections',
                                    'Only remove from collections:'
                                )}
                            </label>
                            {mode === 'collections' ? (
                                <div className="ml-6 flex flex-col gap-1.5">
                                    {collections.map(c => (
                                        <LabeledControl
                                            key={c.id}
                                            label={
                                                <CollectionChip
                                                    collection={c}
                                                    absolute
                                                    size="sm"
                                                />
                                            }
                                        >
                                            <Checkbox
                                                checked={selectedCollections.includes(
                                                    c.id
                                                )}
                                                onCheckedChange={v =>
                                                    setSelectedCollections(
                                                        prev =>
                                                            v
                                                                ? [
                                                                      ...prev,
                                                                      c.id,
                                                                  ]
                                                                : prev.filter(
                                                                      x =>
                                                                          x !==
                                                                          c.id
                                                                  )
                                                    )
                                                }
                                            />
                                        </LabeledControl>
                                    ))}
                                </div>
                            ) : null}
                        </RadioGroup>
                    ) : null}
                    {needsTyping ? (
                        <div>
                            <p className="mb-1 text-sm text-muted-foreground">
                                {t(
                                    'confirm.type_to_confirm',
                                    'Type "{{text}}" to confirm:',
                                    {text: confirmWord}
                                )}
                            </p>
                            <Input
                                autoFocus
                                value={typed}
                                onChange={e => setTyped(e.target.value)}
                            />
                        </div>
                    ) : null}
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={submit}
                        disabled={
                            disabled ||
                            (prepare.data
                                ? !prepare.data.canDelete &&
                                  collections.length === 0
                                : true)
                        }
                        loading={loading}
                    >
                        <Trash2Icon /> {t('common.delete', 'Delete')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

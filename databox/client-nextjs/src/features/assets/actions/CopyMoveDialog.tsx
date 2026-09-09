'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CopyIcon, FolderInputIcon, InfoIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {Checkbox, LabeledControl, Switch} from '@/components/ui/controls';
import {Alert} from '@/components/ui/misc';
import {
    CollectionTreePicker,
    TreeSelection,
} from '@/components/form/CollectionTreePicker';
import {copyAssets, moveAssets} from '@/lib/api/assets';

type Props = ModalProps & {
    assets: Asset[];
    mode: 'copy' | 'move';
    onComplete?: () => void;
};

export function CopyMoveDialog({
    open,
    onOpenChange,
    assets,
    mode,
    onComplete,
}: Props) {
    const {t} = useTranslation();
    const [destination, setDestination] = useState<TreeSelection>();
    const [byReference, setByReference] = useState(true);
    const [withAttributes, setWithAttributes] = useState(true);
    const [withTags, setWithTags] = useState(true);
    const [loading, setLoading] = useState(false);

    const ids = assets.map(a => a.id);
    const sourceWorkspaces = new Set(assets.map(a => a.workspace.id));
    const crossWorkspace = destination
        ? [...sourceWorkspaces].some(w => w !== destination.workspaceId)
        : false;

    const submit = async () => {
        if (!destination) {
            return;
        }
        setLoading(true);
        try {
            if (mode === 'move') {
                await moveAssets(ids, destination.iri);
                toast.success(
                    t('asset.move.done', '{{count}} asset(s) moved', {
                        count: ids.length,
                    })
                );
            } else {
                await copyAssets(
                    ids,
                    destination.iri,
                    byReference && !crossWorkspace,
                    {withAttributes, withTags}
                );
                toast.success(
                    t('asset.copy.done', '{{count}} asset(s) copied', {
                        count: ids.length,
                    })
                );
            }
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
            <DialogContent size="md">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'move'
                            ? t('asset.move.title', 'Move {{count}} asset(s)', {
                                  count: ids.length,
                              })
                            : t('asset.copy.title', 'Copy {{count}} asset(s)', {
                                  count: ids.length,
                              })}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody className="space-y-4">
                    <div>
                        <p className="mb-2 text-sm font-medium">
                            {t('asset.copy.destination', 'Destination')}
                        </p>
                        <CollectionTreePicker
                            value={destination}
                            onChange={setDestination}
                            requireCapability="createAsset"
                            allowCreate
                        />
                    </div>
                    {mode === 'copy' ? (
                        <div className="space-y-3">
                            <LabeledControl
                                label={t(
                                    'asset.copy.by_reference',
                                    'Copy by reference (shortcut)'
                                )}
                                description={t(
                                    'asset.copy.by_reference_help',
                                    'The same asset appears in the destination collection. Uncheck to duplicate the asset.'
                                )}
                            >
                                <Switch
                                    checked={byReference && !crossWorkspace}
                                    disabled={crossWorkspace}
                                    onCheckedChange={setByReference}
                                />
                            </LabeledControl>
                            {crossWorkspace ? (
                                <Alert variant="info" icon={<InfoIcon />}>
                                    {t(
                                        'asset.copy.cross_workspace',
                                        'Assets cannot be linked across workspaces: they will be duplicated.'
                                    )}
                                </Alert>
                            ) : null}
                            {!byReference || crossWorkspace ? (
                                <div className="flex flex-col gap-2 pl-1">
                                    <LabeledControl
                                        label={t(
                                            'asset.copy.with_attributes',
                                            'Copy attributes'
                                        )}
                                    >
                                        <Checkbox
                                            checked={withAttributes}
                                            onCheckedChange={v =>
                                                setWithAttributes(v === true)
                                            }
                                        />
                                    </LabeledControl>
                                    <LabeledControl
                                        label={t(
                                            'asset.copy.with_tags',
                                            'Copy tags'
                                        )}
                                    >
                                        <Checkbox
                                            checked={withTags}
                                            onCheckedChange={v =>
                                                setWithTags(v === true)
                                            }
                                        />
                                    </LabeledControl>
                                </div>
                            ) : null}
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
                        onClick={submit}
                        disabled={!destination}
                        loading={loading}
                    >
                        {mode === 'move' ? <FolderInputIcon /> : <CopyIcon />}
                        {mode === 'move'
                            ? t('asset.actions.move', 'Move')
                            : t('asset.actions.copy', 'Copy')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

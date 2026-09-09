'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
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
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {getSavedSearches} from '@/lib/api/misc';

/**
 * Options form of a CMS widget (attributes of the TipTap node).
 */
export function WidgetOptionsDialog({
    open,
    onOpenChange,
    type,
    attrs,
    onChange,
}: ModalProps & {
    type: string;
    attrs: Record<string, any>;
    onChange: (attrs: Record<string, any>) => void;
}) {
    const {t} = useTranslation();
    const [form, setForm] = useState<Record<string, any>>({...attrs});
    const set = (k: string, v: unknown) => setForm(f => ({...f, [k]: v}));
    const savedSearches = useQuery({
        queryKey: ['saved-searches', 'widget'],
        queryFn: () => getSavedSearches(),
        enabled: type === 'searchGridWidget',
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {t('cms.widget.options', 'Options')}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody className="space-y-3">
                    {type === 'assetWidget' ? (
                        <FormRow label={t('cms.widget.asset_id', 'Asset ID')}>
                            <Input
                                value={form.assetId ?? ''}
                                onChange={e => set('assetId', e.target.value)}
                                className="font-mono"
                            />
                        </FormRow>
                    ) : null}
                    {type === 'carouselWidget' || type === 'gridWidget' ? (
                        <FormRow
                            label={t(
                                'cms.widget.asset_ids',
                                'Asset IDs (one per line)'
                            )}
                        >
                            <Textarea
                                value={(form.assetIds ?? []).join('\n')}
                                onChange={e =>
                                    set(
                                        'assetIds',
                                        e.target.value
                                            .split('\n')
                                            .map(s => s.trim())
                                            .filter(Boolean)
                                    )
                                }
                                className="font-mono text-xs"
                            />
                        </FormRow>
                    ) : null}
                    {type === 'searchGridWidget' ? (
                        <>
                            <FormRow
                                label={t(
                                    'saved_search.list.title',
                                    'Saved searches'
                                )}
                            >
                                <SimpleSelect
                                    value={form.savedSearchId ?? undefined}
                                    onValueChange={v => set('savedSearchId', v)}
                                    options={(
                                        savedSearches.data?.items ?? []
                                    ).map(s => ({value: s.id, label: s.name}))}
                                    placeholder={t('common.select', 'Select…')}
                                />
                            </FormRow>
                            <div className="grid grid-cols-3 gap-2">
                                <FormRow
                                    label={t(
                                        'cms.widget.max_items',
                                        'Max items'
                                    )}
                                >
                                    <Input
                                        type="number"
                                        value={form.maxItems ?? 20}
                                        onChange={e =>
                                            set(
                                                'maxItems',
                                                Number(e.target.value)
                                            )
                                        }
                                    />
                                </FormRow>
                                <FormRow
                                    label={t(
                                        'display.thumb_size',
                                        'Thumbnail size'
                                    )}
                                >
                                    <Input
                                        type="number"
                                        value={form.thumbSize ?? 200}
                                        onChange={e =>
                                            set(
                                                'thumbSize',
                                                Number(e.target.value)
                                            )
                                        }
                                    />
                                </FormRow>
                                <FormRow
                                    label={t('cms.widget.height', 'Height')}
                                >
                                    <Input
                                        type="number"
                                        value={form.height ?? 600}
                                        onChange={e =>
                                            set(
                                                'height',
                                                Number(e.target.value)
                                            )
                                        }
                                    />
                                </FormRow>
                            </div>
                            <LabeledControl
                                label={t(
                                    'cms.widget.open_asset',
                                    'Open asset on click'
                                )}
                            >
                                <Switch
                                    checked={form.openAsset !== false}
                                    onCheckedChange={v => set('openAsset', v)}
                                />
                            </LabeledControl>
                        </>
                    ) : null}
                    {type === 'spacerWidget' ? (
                        <FormRow label={t('cms.widget.height', 'Height')}>
                            <Input
                                type="number"
                                value={form.height ?? 40}
                                onChange={e =>
                                    set('height', Number(e.target.value))
                                }
                            />
                        </FormRow>
                    ) : null}
                    {type === 'headerBarWidget' ? (
                        <>
                            <FormRow label={t('cms.title', 'Title')}>
                                <Input
                                    value={form.title ?? ''}
                                    onChange={e => set('title', e.target.value)}
                                />
                            </FormRow>
                            <div className="grid grid-cols-2 gap-2">
                                <FormRow
                                    label={t(
                                        'cms.widget.background',
                                        'Background'
                                    )}
                                >
                                    <Input
                                        value={form.background ?? ''}
                                        onChange={e =>
                                            set('background', e.target.value)
                                        }
                                        placeholder="#rrggbb"
                                    />
                                </FormRow>
                                <FormRow
                                    label={t('cms.widget.color', 'Text color')}
                                >
                                    <Input
                                        value={form.color ?? ''}
                                        onChange={e =>
                                            set('color', e.target.value)
                                        }
                                        placeholder="#rrggbb"
                                    />
                                </FormRow>
                            </div>
                        </>
                    ) : null}
                    {type === 'footerWidget' ? (
                        <FormRow label={t('cms.widget.text', 'Text')}>
                            <Textarea
                                value={form.text ?? ''}
                                onChange={e => set('text', e.target.value)}
                            />
                        </FormRow>
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
                        onClick={() => {
                            onChange(form);
                            onOpenChange(false);
                        }}
                    >
                        {t('common.apply', 'Apply')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

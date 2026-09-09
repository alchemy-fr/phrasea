'use client';

import {useTranslation} from 'react-i18next';
import {LayoutGridIcon, ListIcon, SlidersHorizontalIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/overlays';
import {Slider, Switch} from '@/components/ui/controls';
import {Label} from '@/components/ui/input';
import {Tabs, TabsList, TabsTrigger} from '@/components/ui/misc';
import {
    DisplayPreferences,
    useDisplayPreferences,
    usePreferencesStore,
} from '@/features/preferences/store';

export function DisplayOptionsMenu({
    prefKey = 'display',
}: {
    prefKey?: 'display' | 'displayBatchEdit';
}) {
    const {t} = useTranslation();
    const display = useDisplayPreferences(prefKey);
    const updatePreference = usePreferencesStore(s => s.updatePreference);

    const patch = (p: Partial<DisplayPreferences>) =>
        updatePreference(prefKey, prev => ({
            ...display,
            ...(prev ?? {}),
            ...p,
        }));
    const patchPreview = (p: Partial<DisplayPreferences['previewOptions']>) =>
        patch({previewOptions: {...display.previewOptions, ...p}});

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('display.settings', 'Display settings')}
                >
                    <SlidersHorizontalIcon />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-72 space-y-4">
                <div>
                    <Label className="mb-2">
                        {t('display.layout', 'Layout')}
                    </Label>
                    <Tabs
                        value={display.layout}
                        onValueChange={v =>
                            patch({layout: v as DisplayPreferences['layout']})
                        }
                    >
                        <TabsList className="w-full">
                            <TabsTrigger value="grid">
                                <LayoutGridIcon /> {t('display.grid', 'Grid')}
                            </TabsTrigger>
                            <TabsTrigger value="list">
                                <ListIcon /> {t('display.list', 'List')}
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>
                <div>
                    <Label className="mb-2 justify-between">
                        {t('display.thumb_size', 'Thumbnail size')}
                        <span className="text-xs text-muted-foreground">
                            {display.thumbSize}px
                        </span>
                    </Label>
                    <Slider
                        min={60}
                        max={400}
                        step={10}
                        value={[display.thumbSize]}
                        onValueChange={([v]) => patch({thumbSize: v})}
                    />
                </div>
                <div className="space-y-3 border-t pt-3">
                    <Row
                        label={t(
                            'display.preview_hover',
                            'Display preview on hover'
                        )}
                        checked={display.displayPreview}
                        onChange={v => patch({displayPreview: v})}
                    />
                    <Row
                        label={t(
                            'display.autoplay',
                            'Auto play video previews'
                        )}
                        checked={display.playVideos}
                        onChange={v => patch({playVideos: v})}
                    />
                    {display.displayPreview ? (
                        <>
                            <Row
                                label={t(
                                    'display.preview_file',
                                    'Display file in preview'
                                )}
                                checked={display.previewOptions.displayFile}
                                onChange={v => patchPreview({displayFile: v})}
                            />
                            <Row
                                label={t(
                                    'display.preview_attributes',
                                    'Display attributes in preview'
                                )}
                                checked={
                                    display.previewOptions.displayAttributes
                                }
                                onChange={v =>
                                    patchPreview({displayAttributes: v})
                                }
                            />
                            <div>
                                <Label className="mb-2 justify-between">
                                    {t('display.preview_size', 'Preview size')}
                                    <span className="text-xs text-muted-foreground">
                                        {Math.round(
                                            display.previewOptions.sizeRatio *
                                                100
                                        )}
                                        %
                                    </span>
                                </Label>
                                <Slider
                                    min={20}
                                    max={80}
                                    step={5}
                                    value={[
                                        display.previewOptions.sizeRatio * 100,
                                    ]}
                                    onValueChange={([v]) =>
                                        patchPreview({sizeRatio: v / 100})
                                    }
                                />
                            </div>
                            {display.previewOptions.displayFile &&
                            display.previewOptions.displayAttributes ? (
                                <div>
                                    <Label className="mb-2 justify-between">
                                        {t(
                                            'display.preview_ratio',
                                            'File / attributes ratio'
                                        )}
                                        <span className="text-xs text-muted-foreground">
                                            {Math.round(
                                                display.previewOptions
                                                    .attributesRatio * 100
                                            )}
                                            %
                                        </span>
                                    </Label>
                                    <Slider
                                        min={20}
                                        max={80}
                                        step={5}
                                        value={[
                                            display.previewOptions
                                                .attributesRatio * 100,
                                        ]}
                                        onValueChange={([v]) =>
                                            patchPreview({
                                                attributesRatio: v / 100,
                                            })
                                        }
                                    />
                                </div>
                            ) : null}
                        </>
                    ) : null}
                </div>
            </PopoverContent>
        </Popover>
    );
}

function Row({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
}) {
    return (
        <label className="flex cursor-pointer items-center justify-between gap-3 text-sm">
            <span>{label}</span>
            <Switch checked={checked} onCheckedChange={onChange} />
        </label>
    );
}

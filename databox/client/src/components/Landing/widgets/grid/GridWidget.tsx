import {TFunction} from '@alchemy/i18n';
import {
    RenderWidgetOptionsProps,
    RenderWidgetProps,
    WidgetInterface,
} from '../widgetTypes.ts';
import {Checkbox, InputLabel, Skeleton, TextField} from '@mui/material';
import WidgetOptionsDialogWrapper from '../components/WidgetOptionsDialogWrapper.tsx';
import SavedSearchSelect from '../../../Form/SavedSearchSelect.tsx';
import {useTranslation} from 'react-i18next';
import {Asset} from '../../../../types.ts';
import React, {useState} from 'react';
import {getAssets} from '../../../../api/asset.ts';
import {AssetFile, MemoizedFilePlayer} from '@alchemy/phrasea-framework';
import {ColorPicker, FormRow} from '@alchemy/react-form';
import GridStructure, {
    GridClasses,
    GridStructureProps,
} from './GridStructure.tsx';
import {useOpenAsset} from '../../../AssetSearch/useOpenAsset.ts';

type Props = {
    searchId?: string;
    maxItems?: number;
    size: number;
    gap: number;
    backgroundColor?: string;
    openAsset: boolean;
};

const GridWidget: WidgetInterface<Props> = {
    name: 'grid',

    getTitle(t: TFunction): string {
        return t('editor.widgets.grid.title', 'Grid');
    },

    component: Component,
    optionsComponent: Options,

    defaultOptions: {
        size: 150,
        maxItems: 50,
        gap: 1,
        openAsset: true,
    },
};

export default GridWidget;

function Component({options}: RenderWidgetProps<Props>) {
    const {searchId, size, maxItems, gap, backgroundColor, openAsset} = options;
    const [data, setData] = useState<Asset[]>();

    React.useEffect(() => {
        if (searchId) {
            getAssets({
                savedSearch: searchId,
            }).then(r => {
                setData(r.result.slice(0, maxItems));
            });
        } else {
            setData(undefined);
        }
    }, [searchId]);

    const structureProps: GridStructureProps = {
        size,
        gap,
        backgroundColor,
    };

    const assets: Asset[] | undefined = data
        ?.filter(asset => {
            if (!asset.thumbnail) {
                // eslint-disable-next-line no-console
                console.warn(
                    `Asset ${asset.id} does not have a thumbnail, skipping.`
                );
                return false;
            }

            return true;
        })
        .slice(0, maxItems);

    const openAssetHandler = useOpenAsset({
        assets,
    });

    if (!assets) {
        return (
            <GridStructure {...structureProps}>
                {Array(maxItems)
                    .fill(0)
                    .map((_, i) => (
                        <Skeleton
                            key={i}
                            variant={'rectangular'}
                            width={size}
                            height={size}
                        />
                    ))}
            </GridStructure>
        );
    }
    return (
        <GridStructure {...structureProps}>
            {assets.map(asset => {
                const canOpen = openAsset && !!asset.main;

                return (
                    <div
                        className={GridClasses.Asset}
                        key={asset.id}
                        style={{
                            cursor: canOpen ? 'pointer' : undefined,
                        }}
                        onClick={
                            canOpen
                                ? () => openAssetHandler(asset, asset.main!.id)
                                : undefined
                        }
                    >
                        <MemoizedFilePlayer
                            file={asset.thumbnail!.file as AssetFile}
                            title={asset.resolvedTitle}
                            dimensions={{
                                width: size,
                                height: size,
                            }}
                        />
                    </div>
                );
            })}
        </GridStructure>
    );
}

function Options({
    options,
    updateOptions,
    ...props
}: RenderWidgetOptionsProps<Props>) {
    const {t} = useTranslation();

    return (
        <WidgetOptionsDialogWrapper {...props}>
            <FormRow>
                <SavedSearchSelect
                    label={t(
                        'editor.widgets.grid.options.searchId.label',
                        'Saved Search'
                    )}
                    value={options.searchId as any}
                    onChange={newValue => {
                        updateOptions({
                            searchId: newValue?.value,
                        });
                    }}
                />
            </FormRow>
            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.grid.options.maxItems.label',
                        'Max items'
                    )}
                    type="number"
                    value={options.maxItems}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                maxItems: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.grid.options.size.label',
                        'Size (px)'
                    )}
                    type="number"
                    value={options.size}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                size: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <TextField
                    label={t('editor.widgets.grid.options.gap.label', 'Gap')}
                    type="number"
                    value={options.gap}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                gap: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <ColorPicker
                    label={t(
                        'editor.widgets.grid.options.backgroundColor.label',
                        'Background color'
                    )}
                    color={options.backgroundColor}
                    onChange={newColor => {
                        updateOptions({
                            backgroundColor: newColor,
                        });
                    }}
                />
            </FormRow>
            <FormRow>
                <InputLabel>
                    <Checkbox
                        checked={options.openAsset}
                        onChange={e => {
                            updateOptions({
                                openAsset: e.target.checked,
                            });
                        }}
                    />
                    {t(
                        'editor.widgets.grid.options.openAsset.label',
                        'Open asset on click'
                    )}
                </InputLabel>
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}

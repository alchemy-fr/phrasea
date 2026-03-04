import {TFunction} from '@alchemy/i18n';
import {
    RenderWidgetOptionsProps,
    RenderWidgetProps,
    WidgetInterface,
} from '../widgetTypes.ts';
import {
    FormControl,
    FormControlLabel,
    FormLabel,
    Radio,
    RadioGroup,
    Skeleton,
    TextField,
} from '@mui/material';
import WidgetOptionsDialogWrapper from '../components/WidgetOptionsDialogWrapper.tsx';
import {useTranslation} from 'react-i18next';
import {Asset} from '../../../../types.ts';
import React, {useState} from 'react';
import {getAsset} from '../../../../api/asset.ts';
import {AssetFile, MemoizedFilePlayer} from '@alchemy/phrasea-framework';
import {FormRow} from '@alchemy/react-form';
import AssetStructure from './AssetStructure.tsx';
import {useOpenAsset} from '../../../AssetSearch/useOpenAsset.ts';
import {AssetWidgetProps} from './types.ts';

const AssetWidget: WidgetInterface<AssetWidgetProps> = {
    name: 'asset',

    getTitle(t: TFunction): string {
        return t('editor.widgets.asset.title', 'Asset');
    },

    component: Component,
    optionsComponent: Options,

    defaultOptions: {
        maxWidth: 300,
        maxHeight: 300,
        gap: 1,
        openOnClick: false,
        imagePosition: 'left',
        borderRadius: 4,
    },
};

export default AssetWidget;

function Component({options}: RenderWidgetProps<AssetWidgetProps>) {
    const {assetId, maxWidth, maxHeight, openOnClick} = options;
    const [data, setData] = useState<Asset>();

    React.useEffect(() => {
        if (assetId) {
            getAsset(assetId).then(setData);
        } else {
            setData(undefined);
        }
    }, [assetId]);

    const structureProps = options;

    const openAsset = useOpenAsset({});

    if (!data?.preview?.file) {
        return (
            <AssetStructure {...structureProps}>
                <Skeleton
                    variant="rectangular"
                    width={maxWidth}
                    height={maxHeight}
                />
            </AssetStructure>
        );
    }

    return (
        <AssetStructure {...structureProps}>
            <div
                key={data.id}
                style={{
                    cursor: openOnClick ? 'pointer' : undefined,
                }}
                onClick={
                    openOnClick && !!data.main
                        ? () => openAsset(data, data.main!.id)
                        : undefined
                }
            >
                <MemoizedFilePlayer
                    file={data.preview!.file as AssetFile}
                    title={data.resolvedTitle}
                    dimensions={{
                        width: maxWidth,
                        height: maxHeight,
                    }}
                />
            </div>
        </AssetStructure>
    );
}

function Options({
    options,
    updateOptions,
    ...props
}: RenderWidgetOptionsProps<AssetWidgetProps>) {
    const {t} = useTranslation();

    return (
        <WidgetOptionsDialogWrapper {...props}>
            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.asset.options.assetId.label',
                        'Asset ID'
                    )}
                    value={options.assetId}
                    onChange={e => {
                        updateOptions({
                            assetId: e.target.value,
                        });
                    }}
                />
            </FormRow>
            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.asset.options.maxWidth.label',
                        'Max Width (px)'
                    )}
                    type="number"
                    value={options.maxWidth}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                maxWidth: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <TextField
                    label={t(
                        'editor.widgets.asset.options.maxHeight.label',
                        'Max Height (px)'
                    )}
                    type="number"
                    value={options.maxHeight}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                maxHeight: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <TextField
                    label={t('editor.widgets.asset.options.gap.label', 'Gap')}
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
                <TextField
                    label={t(
                        'editor.widgets.asset.options.borderRadius.label',
                        'Border Radius (px)'
                    )}
                    type="number"
                    value={options.borderRadius}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                borderRadius: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <FormControl>
                    <FormLabel id={'image-position-label'}>
                        {t(
                            'editor.widgets.asset.options.imagePosition.label',
                            'Image Position'
                        )}
                    </FormLabel>
                    <RadioGroup
                        aria-labelledby="image-position-label"
                        defaultValue="left"
                        name="radio-buttons-group"
                        onChange={e => {
                            updateOptions({
                                imagePosition: e.target.value as
                                    | 'left'
                                    | 'right',
                            });
                        }}
                    >
                        <FormControlLabel
                            value="left"
                            control={<Radio />}
                            label={t(
                                'editor.widgets.asset.options.imagePosition.left',
                                'Left'
                            )}
                        />
                        <FormControlLabel
                            value="right"
                            control={<Radio />}
                            label={t(
                                'editor.widgets.asset.options.imagePosition.right',
                                'Right'
                            )}
                        />
                        <FormControlLabel
                            value="top"
                            control={<Radio />}
                            label={t(
                                'editor.widgets.asset.options.imagePosition.top',
                                'Top'
                            )}
                        />
                        <FormControlLabel
                            value="bottom"
                            control={<Radio />}
                            label={t(
                                'editor.widgets.asset.options.imagePosition.bottom',
                                'Bottom'
                            )}
                        />
                    </RadioGroup>
                </FormControl>
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}

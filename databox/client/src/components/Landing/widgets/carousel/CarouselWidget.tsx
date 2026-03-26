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
import {AssetFile, FilePlayer} from '@alchemy/phrasea-framework';
import {ColorPicker, FormRow} from '@alchemy/react-form';
import CarouselStructure from './CarouselStructure.tsx';
import {useOpenAsset} from '../../../AssetSearch/useOpenAsset.ts';

type Props = {
    searchId?: string;
    maxItems: number;
    height: number;
    backgroundColor?: string;
    cover: boolean;
    openAsset: boolean;
};

const CarouselWidget: WidgetInterface<Props> = {
    name: 'carousel',

    getTitle(t: TFunction): string {
        return t('editor.widgets.carousel.title', 'Carousel');
    },

    component: Component,
    optionsComponent: Options,

    defaultOptions: {
        height: 300,
        maxItems: 10,
        cover: true,
        openAsset: false,
    },
};

export default CarouselWidget;

function Component({options}: RenderWidgetProps<Props>) {
    const {searchId, height, maxItems, backgroundColor, cover} = options;
    const [data, setData] = useState<Asset[]>();

    React.useEffect(() => {
        if (searchId) {
            getAssets({
                savedSearch: options.searchId,
                limit: maxItems,
            }).then(r => {
                setData(r.result.slice(0, maxItems));
            });
        } else {
            setData(undefined);
        }
    }, [searchId]);

    const structureProps = {
        height,
        backgroundColor,
    };

    const assets: Asset[] | undefined = data
        ?.filter(asset => {
            if (!asset.preview) {
                // eslint-disable-next-line no-console
                console.warn(
                    `Asset ${asset.id} does not have a preview, skipping.`
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
            <CarouselStructure {...structureProps} itemsCount={maxItems}>
                <Skeleton
                    variant={'rectangular'}
                    width={'100%'}
                    height={height}
                />
            </CarouselStructure>
        );
    }

    return (
        <CarouselStructure
            {...structureProps}
            delay={5000}
            itemsCount={assets.length}
        >
            {assets.map(asset => {
                const canOpen = options.openAsset && !!asset.main;

                return (
                    <div
                        key={asset.id}
                        style={{
                            width: '100%',
                            display: 'flex',
                            justifyContent: 'center',
                            alignItems: 'center',
                            cursor: canOpen ? 'pointer' : undefined,
                        }}
                        onClick={
                            canOpen
                                ? () => openAssetHandler(asset, asset.main!.id)
                                : undefined
                        }
                    >
                        <FilePlayer
                            cover={cover}
                            file={asset.preview!.file as AssetFile}
                            title={asset.resolvedTitle}
                            dimensions={{
                                width: 300,
                                height,
                            }}
                            trackingId={asset.trackingId}
                        />
                    </div>
                );
            })}
        </CarouselStructure>
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
                        'editor.widgets.carousel.options.searchId.label',
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
                        'editor.widgets.carousel.options.maxItems.label',
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
                        'editor.widgets.carousel.options.height.label',
                        'Height (px)'
                    )}
                    type="number"
                    value={options.height}
                    onChange={e => {
                        const value = parseInt(e.target.value, 10);
                        if (!isNaN(value)) {
                            updateOptions({
                                height: value,
                            });
                        }
                    }}
                />
            </FormRow>
            <FormRow>
                <ColorPicker
                    label={t(
                        'editor.widgets.carousel.options.backgroundColor.label',
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
                        checked={options.cover}
                        onChange={e => {
                            updateOptions({
                                cover: e.target.checked,
                            });
                        }}
                    />
                    {t(
                        'editor.widgets.carousel.options.cover.label',
                        'Cover mode'
                    )}
                </InputLabel>
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
                        'editor.widgets.carousel.options.openAsset.label',
                        'Open asset on click'
                    )}
                </InputLabel>
            </FormRow>
        </WidgetOptionsDialogWrapper>
    );
}

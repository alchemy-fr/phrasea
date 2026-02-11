import {TFunction} from '@alchemy/i18n';
import {
    RenderWidgetOptionsProps,
    RenderWidgetProps,
    WidgetInterface,
} from '../widgetTypes.ts';
import {Skeleton} from '@mui/material';
import WidgetOptionsDialogWrapper from '../components/WidgetOptionsDialogWrapper.tsx';
import SavedSearchSelect from '../../../Form/SavedSearchSelect.tsx';
import {useTranslation} from 'react-i18next';
import {Asset} from '../../../../types.ts';
import React, {useState} from 'react';
import {getAssets} from '../../../../api/asset.ts';

type Props = {
    searchId?: string;
    maxItems?: number;
};

const CarouselWidget: WidgetInterface<Props> = {
    name: 'carousel',

    getTitle(t: TFunction): string {
        return t('editor.widgets.carousel.title', 'Carousel');
    },

    component: Component,
    optionsComponent: Options,
};

export default CarouselWidget;

function Component({options}: RenderWidgetProps<Props>) {
    const {searchId, maxItems = 3} = options;
    const [data, setData] = useState<Asset[]>();

    React.useEffect(() => {
        if (searchId) {
            getAssets({}).then(r => {
                setData(r.result.slice(0, maxItems));
            });
        }
    }, [searchId]);

    if (!data) {
        return (
            <div>
                <Skeleton />
                <Skeleton />
                <Skeleton />
            </div>
        );
    }

    return (
        <div>
            {data.map(asset => (
                <div key={asset.id}>{asset.resolvedTitle}</div>
            ))}
        </div>
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
        </WidgetOptionsDialogWrapper>
    );
}

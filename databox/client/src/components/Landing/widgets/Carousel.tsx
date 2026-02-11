import {TFunction} from '@alchemy/i18n';
import {
    RenderWidgetOptionsProps,
    RenderWidgetProps,
    WidgetInterface,
} from './widgetTypes.ts';
import {Skeleton, TextField} from '@mui/material';
import WidgetOptionsDialogWrapper from './components/WidgetOptionsDialogWrapper.tsx';

type Props = {
    searchId?: string;
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
    return (
        <div>
            <pre>{JSON.stringify(options, null, 2)}</pre>
            <Skeleton />
            <Skeleton />
            <Skeleton />
        </div>
    );
}

function Options({
    title,
    options,
    updateOptions,
}: RenderWidgetOptionsProps<Props>) {
    return (
        <WidgetOptionsDialogWrapper title={title}>
            <TextField
                label="Search ID"
                value={options.searchId || ''}
                onChange={e => {
                    updateOptions({
                        searchId: e.target.value,
                    });
                }}
            />
        </WidgetOptionsDialogWrapper>
    );
}

import {TFunction} from '@alchemy/i18n';
import {
    RenderWidgetOptionsProps,
    RenderWidgetProps,
    WidgetInterface,
} from './widgetTypes.ts';
import {TextField} from '@mui/material';

type Props = {
    searchId?: string;
};

const CarouselWidget: WidgetInterface<Props> = {
    name: 'carousel',

    getTitle(t: TFunction): string {
        return t('editor.widgets.carousel.title', 'Carousel');
    },

    component: Component,
    optionsComponent: RenderOptions,
};

export default CarouselWidget;

function Component({options}: RenderWidgetProps<Props>) {
    return (
        <div className="carousel-widget">
            <p>Carousel Widget "{options.searchId}"</p>
        </div>
    );
}

function RenderOptions({
    options,
    updateOptions,
}: RenderWidgetOptionsProps<Props>) {
    return (
        <div className="carousel-widget-options">
            <p>Carousel Widget Options</p>
            <TextField
                label="Search ID"
                value={options.searchId || ''}
                onChange={e => {
                    updateOptions({
                        searchId: e.target.value,
                    });
                }}
            />
        </div>
    );
}

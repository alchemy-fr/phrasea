import CarouselWidget from './carousel/CarouselWidget.tsx';
import GridWidget from './grid/GridWidget.tsx';
import AssetWidget from './asset/AssetWidget.tsx';
import SpacerWidget from './SpacerWidget.tsx';
import {WidgetInterface} from './widgetTypes.ts';

export const widgets: WidgetInterface<any>[] = [
    AssetWidget,
    CarouselWidget,
    GridWidget,
    SpacerWidget,
];

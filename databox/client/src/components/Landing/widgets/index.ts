import CarouselWidget from './carousel/CarouselWidget.tsx';
import GridWidget from './grid/GridWidget.tsx';
import AssetWidget from './asset/AssetWidget.tsx';
import SpacerWidget from './SpacerWidget.tsx';
import {WidgetInterface} from './widgetTypes.ts';
import HeaderBarWidget from './header-bar/HeaderBarWidget.tsx';
import SearchGridWidget from './search-grid/SearchGridWidget.tsx';

export const widgets: WidgetInterface<any>[] = [
    AssetWidget,
    CarouselWidget,
    GridWidget,
    SearchGridWidget,
    SpacerWidget,
    HeaderBarWidget,
];

import CarouselWidget from './carousel/CarouselWidget.tsx';
import GridWidget from './grid/GridWidget.tsx';
import {WidgetInterface} from './widgetTypes.ts';

export const widgets: WidgetInterface<any>[] = [CarouselWidget, GridWidget];

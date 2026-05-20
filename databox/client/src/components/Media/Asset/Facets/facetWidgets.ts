import {FacetGroupProps, FacetType} from './facetTypes.ts';
import React from 'react';
import TextFacet from './types/TextFacet.tsx';
import BooleanFacet from './types/BooleanFacet.tsx';
import DateHistogramFacet from './types/DateHistogramFacet.tsx';
import GeoDistanceFacet from './types/GeoDistanceFacet.tsx';
import EntitiesFacet from './types/EntitiesFacet.tsx';
import {BuiltInField} from '../../Search/search.ts';
import TagsFacet from './types/TagsFacet.tsx';

export const facetWidgets: Record<FacetType, React.FC<FacetGroupProps>> = {
    [FacetType.Text]: TextFacet,
    [FacetType.Boolean]: BooleanFacet,
    [FacetType.DateRange]: DateHistogramFacet,
    [FacetType.GeoDistance]: GeoDistanceFacet,
    [FacetType.Entity]: EntitiesFacet,
};
export const facetWidgetsByKey: Record<string, React.FC<FacetGroupProps>> = {
    [BuiltInField.Tag]: TagsFacet,
    [BuiltInField.IsStory]: BooleanFacet,
};

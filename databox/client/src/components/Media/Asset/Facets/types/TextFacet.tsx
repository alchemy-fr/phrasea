import ListFacet from './ListFacet.tsx';
import TextFacetItem from './TextFacetItem.tsx';
import {FacetGroupProps} from '../facetTypes.ts';

export default function TextFacet(props: FacetGroupProps) {
    return <ListFacet {...props} itemComponent={TextFacetItem} />;
}

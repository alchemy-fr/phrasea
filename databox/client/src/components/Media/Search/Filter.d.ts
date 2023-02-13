import {FacetType, ResolvedBucketValue} from "../Asset/Facets";
import {AttributeType} from "../../../api/attributes";

export type FilterEntry = {
    t: string; // Attribute title
    x?: FilterType | undefined; // Attribute type if not "text"
    w?: FacetType;
    a: string; // Attribute name
    i?: 1 | undefined; // Inverted
    v: ResolvedBucketValue[];
}

export type Filters = FilterEntry[];

export type SortBy = {
    t: string; // Attribute title
    a: string; // Attribute name
    w: 0 | 1; // ASC=0, DESC=1
    g: boolean; // Grouped in UI
}

export type FilterType = AttributeType | "missing";

import {BucketKeyValue, FacetType} from "../Asset/Facets";

export type FilterEntry = {
    t: string; // Attribute title
    w?: FacetType;
    a: string; // Attribute name
    i?: 1 | undefined; // Inverted
    v: BucketKeyValue[];
}

export type Filters = FilterEntry[];

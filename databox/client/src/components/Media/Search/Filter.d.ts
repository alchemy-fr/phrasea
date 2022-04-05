import {BucketKeyValue} from "../Asset/Facets";

export type FilterEntry = {
    t: string; // Attribute title
    a: string; // Attribute name
    i?: 1 | undefined; // Inverted
    v: BucketKeyValue[];
}

export type Filters = FilterEntry[];

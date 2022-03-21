
type FilterValue = string | number | boolean;

export type FilterEntry = {
    t: string; // Attribute title
    a: string; // Attribute name
    i?: 1 | undefined; // Inverted
    v: FilterValue[];
}

export type Filters = FilterEntry[];

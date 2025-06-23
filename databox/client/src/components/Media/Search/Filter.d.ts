import {AttributeType} from '../../../api/types.ts';

export type SortBy = {
    a: string; // Attribute slug
    w: 0 | 1; // ASC=0, DESC=1
    g: boolean; // Grouped in UI
};

export type FilterType = AttributeType | 'missing';

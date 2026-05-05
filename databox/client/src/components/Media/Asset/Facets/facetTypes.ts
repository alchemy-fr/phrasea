import {AttributeType} from '../../../../api/types.ts';
import {FilterType} from '../../Search/Filter';
import {AttributeFormatterOptions} from '../Attribute/types/types';
import {getAttributeType} from '../Attribute/types';

export enum FacetType {
    Text = 'text',
    Boolean = 'boolean',
    DateRange = 'date_range',
    GeoDistance = 'geo_distance',
    Entity = 'entity',
}
export type BucketValue = string | number | boolean;
export type LabelledBucketValue = {
    label: string;
    value: BucketValue;
    item?: Record<string, any>;
};
export type ResolvedBucketValue = BucketValue | LabelledBucketValue;
export type Bucket = {
    key: BucketValue | LabelledBucketValue;
    doc_count: number;
};
export type Facet = {
    meta: {
        title: string;
        locale?: string;
        widget?: FacetType;
        type?: AttributeType;
        sortable: boolean;
    };
    buckets: Bucket[];
    doc_count_error_upper_bound: number;
    sum_other_doc_count: number;
    missing_count?: number;
    interval?: string;
};
export type TFacets = Record<string, Facet>;

export function extractLabelValueFromKey(
    key: ResolvedBucketValue,
    type: FilterType | undefined,
    formatterOptions: AttributeFormatterOptions
): LabelledBucketValue {
    // eslint-disable-next-line no-prototype-builtins
    if (key && typeof key === 'object' && key.hasOwnProperty('value')) {
        return key as LabelledBucketValue;
    }

    if ('missing' === type) {
        return {
            label: `Missing`,
            value: '__missing__',
        };
    }

    type = type ?? AttributeType.Text;
    const at = getAttributeType(type);

    if ([AttributeType.DateTime, AttributeType.Date].includes(type)) {
        return {
            label: at.formatValueAsString({
                ...formatterOptions,
                value: key,
            })!,
            value: key as BucketValue,
        };
    } else if (type === AttributeType.Boolean) {
        return {
            label: at.formatValueAsString({
                ...formatterOptions,
                value: !!key,
            })!,
            value: !!key,
        };
    }

    return {
        label: at.formatValueAsString({
            ...formatterOptions,
            value: key as string,
        })!,
        value: key as BucketValue,
    };
}

export type FacetGroupProps = {
    facet: Facet;
    name: string;
};

export type FacetPreference = {
    name: string;
    hidden?: true;
    order?: number;
};

export const orderInfinity = 999999;

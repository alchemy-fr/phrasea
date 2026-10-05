import type {FacetBucket, LabelledBucketValue} from '@/types/api';
import type {ScalarValue} from '../aql/types';

/** Bucket key as `{label, value}`: entities are labelled by the API, scalars are not */
export function resolveBucket(bucket: FacetBucket): LabelledBucketValue {
    const key = bucket.key;
    if (key && typeof key === 'object' && 'value' in key) {
        return key;
    }

    return {
        label: String(key),
        value: key as ScalarValue as LabelledBucketValue['value'],
    };
}

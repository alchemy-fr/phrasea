import useCancelRequest, {
    useCancelRequestCallback,
} from './src/useCancelRequest';
import useRequestErrorHandler from './src/useRequestErrorHandler';

import {createHttpClient} from './src/httpClient';
import useFormSubmit from './src/useFormSubmit';
import {getObjectPropertyPath} from './src/form';
import {createIriFromId, extractIdFromIri, getHydraCollection, isEntityIri, normalizeNestedObjects} from './src/hydra';

export {
    useCancelRequest,
    useCancelRequestCallback,
    useRequestErrorHandler,
    createHttpClient,
    useFormSubmit,
    getObjectPropertyPath,
    getHydraCollection,
    normalizeNestedObjects,
    extractIdFromIri,
    createIriFromId,
    isEntityIri,
};
export * from './src/types';
export * from './src/utils';

import useCancelRequest, {
    useCancelRequestCallback,
} from './src/useCancelRequest';
import useRequestErrorHandler from './src/useRequestErrorHandler';

import {createHttpClient} from './src/httpClient';
import useFormSubmit from './src/useFormSubmit';
import {getObjectPropertyPath} from './src/form';
import {
    extractIdFromIri,
    getHydraCollection,
    normalizeNestedObjects,
} from './src/hydra';
import {getFileType} from './src/fileType';

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
    getFileType,
};
export * from './src/types';
export * from './src/utils';
export * from './src/hydra';

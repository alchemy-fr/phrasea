'use client';

import {createContext, useContext} from 'react';
import type {RequestOptions} from '@/lib/api/http';
import type {UploadLinkSession} from '@/types/api';

export type UploadAccess = {
    /** Merged into every call of the upload flow */
    requestOptions?: RequestOptions;
    /** The upload link the wizard runs with; none for a signed-in user */
    link?: UploadLinkSession;
    /** Whose resumable uploads are remembered; the signed-in user by default */
    userKey?: string;
    /** A batch was committed: what is left of the link changed */
    onCommit?: () => void;
};

/** How the upload wizard reaches the API: the signed-in user by default */
export const UploadAccessContext = createContext<UploadAccess>({});

export function useUploadAccess(): UploadAccess {
    return useContext(UploadAccessContext);
}

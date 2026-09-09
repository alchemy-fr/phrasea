'use client';

import {useContext} from 'react';
import {ResultContextInternal, type ResultContextValue} from './ResultProvider';

export function useOptionalResults(): ResultContextValue | null {
    return useContext(ResultContextInternal);
}

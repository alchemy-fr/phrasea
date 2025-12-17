import React from 'react';
import {loadPublication} from '../api/publicationApi.ts';
import {Publication} from '../types.ts';

type Props = {
    id: string;
};

export function usePublication({id}: Props) {
    const [loading, setLoading] = React.useState(false);
    const [errorCode, setErrorCode] = React.useState<number | undefined>();
    const [data, setData] = React.useState<Publication>();
    const [authorizationError, setAuthorizationError] = React.useState<
        string | undefined
    >();

    const load = React.useCallback(async () => {
        setLoading(true);
        try {
            const data = await loadPublication(id);
            setData(data);
        } catch (error: any) {
            const status = error.response?.status;
            if (status) {
                if (
                    status === 403 &&
                    error.response?.data?.authorizationError
                ) {
                    setAuthorizationError(
                        error.response.data.authorizationError
                    );
                }
                setErrorCode(status);
            } else {
                setErrorCode(500);
            }
        } finally {
            setLoading(false);
        }
    }, [id]);

    React.useEffect(() => {
        load();
    }, [load]);

    return {
        loading,
        publication: data,
        errorCode,
        authorizationError,
        load,
    };
}

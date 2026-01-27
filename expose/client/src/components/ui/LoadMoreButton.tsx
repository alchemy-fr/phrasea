import React from 'react';
import {NormalizedCollectionResponse} from '@alchemy/api';
import {LoadMoreRow} from '@alchemy/phrasea-ui';

type Props<T> = {
    data?: NormalizedCollectionResponse<T>;
    load: (nextUrl?: string) => any;
    loading: boolean;
};

export default function LoadMoreButton<T>({loading, load, data}: Props<T>) {
    return (
        <LoadMoreRow
            loading={loading}
            hasMore={!!data?.next}
            onClick={() => {
                load(data!.next!);
            }}
        />
    );
}

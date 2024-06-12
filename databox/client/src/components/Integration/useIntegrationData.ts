import {
    createDefaultPagination,
    createPaginatedLoader,
    Pagination,
} from '../../api/pagination.ts';
import {IntegrationData} from '../../types.ts';
import React from 'react';
import {
    getWorkspaceIntegrationData,
    ObjectType,
} from '../../api/integrations.ts';

type Props = {
    objectType: ObjectType;
    integrationId: string;
    objectId?: string;
    defaultData: IntegrationData[];
};

export function useIntegrationData({
    integrationId,
    objectType,
    objectId,
    defaultData,
}: Props) {
    const [data, setData] = React.useState<Pagination<IntegrationData>>(
        createDefaultPagination(defaultData)
    );

    const load = React.useCallback(
        createPaginatedLoader(
            next =>
                getWorkspaceIntegrationData(integrationId, next, {
                    params: {
                        objectType,
                        objectId,
                    },
                }),
            setData
        ),
        [setData]
    );

    const addData = React.useCallback(
        (newData: IntegrationData) => {
            setData(p => ({
                ...p,
                pages: p.pages.concat([[newData]]),
                total: p.total ? p.total + 1 : 1,
            }));
        },
        [setData]
    );

    const removeData = React.useCallback(
        (id: string) => {
            setData(p => ({
                ...p,
                total: p.total ? p.total - 1 : 0,
                pages: p.pages.map(pa => pa?.filter(i => i.id !== id)),
            }));
        },
        [setData]
    );

    return {
        load,
        data,
        setData,
        addData,
        removeData,
    };
}

import {
    createDefaultPagination,
    createPaginatedLoader,
    Pagination,
} from '../../api/pagination.ts';
import {IntegrationData} from '../../types.ts';
import React from 'react';
import {getWorkspaceIntegrationData, IntegrationType} from '../../api/integrations.ts';

type Props = {
    type: IntegrationType;
    integrationId: string;
    objectId?: string;
    defaultData: IntegrationData[];
};

export function useIntegrationData({
    type,
    integrationId,
    objectId,
    defaultData,
}: Props) {
    const [data, setData] = React.useState<Pagination<IntegrationData>>(
        createDefaultPagination(defaultData)
    );

    const load = React.useCallback(
        createPaginatedLoader(
            next =>
                getWorkspaceIntegrationData(type, integrationId, next, {
                    params: {
                        objectId,
                    },
                }),
            setData
        ),
        [setData]
    );

    const addData = React.useCallback((newData: IntegrationData) => {
            setData(p => ({
                ...p,
                pages: p.pages.concat([newData]),
            }));
        },
        [setData]
    );

    const removeData = React.useCallback((id: string) => {
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

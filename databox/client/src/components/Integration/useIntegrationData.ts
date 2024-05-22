import {createDefaultPagination, createPaginatedLoader, Pagination} from "../../api/pagination.ts";
import {IntegrationData} from "../../types.ts";
import React from "react";
import {getWorkspaceIntegrationData} from "../../api/integrations.ts";

type Props = {
    integrationId: string;
    fileId?: string;
    defaultData: IntegrationData[];
};

export function useIntegrationData({
    integrationId,
    fileId,
    defaultData,
}: Props) {
    const [data, setData] = React.useState<Pagination<IntegrationData>>(createDefaultPagination(defaultData));

    const load = React.useCallback(createPaginatedLoader((next) => getWorkspaceIntegrationData(integrationId, next, {
        params: {
            fileId,
        }
    }), setData), [setData]);

    return {
        load,
        data,
        setData,
    }
}

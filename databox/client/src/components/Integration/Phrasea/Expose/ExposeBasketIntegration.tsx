import {BasketIntegrationActionsProps} from "../../types.ts";
import {LoadingButton} from "@mui/lab";
import React from "react";
import {useIntegrationData} from "../../useIntegrationData.ts";
import {IntegrationType} from "../../../../api/integrations.ts";
import {useIntegrationAuth} from "../../useIntegrationAuth.ts";

type Props = {} & BasketIntegrationActionsProps;

export default function ExposeBasketIntegration({
    integration,
    basket,
}: Props) {
    const {loading, requestAuth} = useIntegrationAuth({
        integrationId: integration.id,
    });
    const {data, load} = useIntegrationData({
        type: IntegrationType.Basket,
        integrationId: integration.id,
        objectId: basket.id,
        defaultData: integration.data,
    });

    return <div>
        <LoadingButton
            onClick={requestAuth}
            loading={loading}
            disabled={loading}
        >
            Authorize
        </LoadingButton>

        {data.pages.length > 0 && (
            data.pages.flat().map(d => {
                return <div
                    key={d.id}
                >
                    {d.id}
                </div>
            })
        )}
    </div>
}

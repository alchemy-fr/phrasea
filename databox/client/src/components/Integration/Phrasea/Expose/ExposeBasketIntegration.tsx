import {BasketIntegrationActionsProps} from "../../types.ts";
import {LoadingButton} from "@mui/lab";
import {useIntegrationData} from "../../useIntegrationData.ts";
import {IntegrationType} from "../../../../api/integrations.ts";
import {useIntegrationAuth} from "../../useIntegrationAuth.ts";

type Props = {} & BasketIntegrationActionsProps;

export default function ExposeBasketIntegration({
    integration,
    basket,
}: Props) {
    const {loading, requestAuth, hasValidToken} = useIntegrationAuth({
        integration,
    });
    const {data} = useIntegrationData({
        type: IntegrationType.Basket,
        integrationId: integration.id,
        objectId: basket.id,
        defaultData: integration.data,
    });

    return <div>
        {!hasValidToken ? <div>
            <LoadingButton
            onClick={requestAuth}
            loading={loading}
            disabled={loading}
        >
            Authorize
        </LoadingButton>
        </div> : ''}

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

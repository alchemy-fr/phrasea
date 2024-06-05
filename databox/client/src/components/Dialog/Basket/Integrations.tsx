import {Basket, WorkspaceIntegration} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import React, {useEffect} from "react";
import {getIntegrationsOfContext, IntegrationContext, ObjectType} from "../../../api/integrations.ts";
import {ListItem, Skeleton} from "@mui/material";
import {BasketIntegrationActionsProps, Integration} from "../../Integration/types.ts";
import ExposeBasketIntegration from "../../Integration/Phrasea/Expose/ExposeBasketIntegration";

type Props = {
    data: Basket;
} & DialogTabProps;

export default function Integrations({data, onClose, minHeight}: Props) {
    const [integrations, setIntegrations] = React.useState<WorkspaceIntegration[]>();

    useEffect(() => {
        getIntegrationsOfContext(IntegrationContext.Basket, undefined, {
            objectType: ObjectType.Basket,
            objectId: data.id,
        }).then(r =>
            setIntegrations(r.result)
        );
    }, []);

    const components: Partial<Record<Integration, React.FC<BasketIntegrationActionsProps>>> = {
        [Integration.PhraseaExpose]: ExposeBasketIntegration,
    };

    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            {integrations ? integrations.map(i => (
                <div
                    key={i.id}
                >
                    {i.title}

                    {components[i.integration] ? React.createElement(components[i.integration]!, {
                        integration: i,
                        basket: data,
                    }) : ''}
                </div>
            )) : <>
                <ListItem>
                    <Skeleton variant={'text'} width={'100%'} />
                </ListItem>
                <ListItem>
                    <Skeleton variant={'text'} width={'100%'} />
                </ListItem>
            </>}
        </ContentTab>
    );
}

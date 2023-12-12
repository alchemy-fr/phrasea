import React from 'react';
import {useLocation, useNavigate, useParams} from '@alchemy/navigation';

export function withRouter<ComponentProps>(
    Component: React.FunctionComponent<ComponentProps>
) {
    function ComponentWithRouterProp(props: ComponentProps) {
        const location = useLocation();
        const navigate = useNavigate();
        const params = useParams();

        return (
            <Component
                {...props}
                location={location}
                navigate={navigate}
                params={params}
            />
        );
    }

    return ComponentWithRouterProp;
}

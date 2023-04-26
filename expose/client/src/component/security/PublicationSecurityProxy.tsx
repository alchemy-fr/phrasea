import React, {PropsWithChildren} from 'react';
import {Publication} from "../../types";
import {securityMethods} from "./methods";
import FullPageLoader from "../FullPageLoader";

type Props = PropsWithChildren<{
    publication: Publication | undefined;
    reload: () => void;
}>;

export default function PublicationSecurityProxy({
    children,
    publication,
    reload,
}: Props) {
    if (!publication) {
        return <FullPageLoader/>
    }

    const {authorized, securityContainerId, authorizationError, securityMethod} = publication!;

    if (authorized) {
        return children as JSX.Element;
    }

    if (authorizationError === 'not_allowed') {
        return <div>
            Sorry! You are not allowed to access this publication.
        </div>
    }

    if (securityMethods[securityMethod]) {
        return React.createElement(securityMethods[securityMethod], {
            error: authorizationError,
            onAuthorization: reload,
            securityContainerId,
        });
    }

    return <div>
        Sorry! You cannot access this publication.
    </div>
}

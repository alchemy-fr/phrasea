import React, {PropsWithChildren} from 'react';
import {Publication} from "../../types";
import {securityMethods} from "./methods";
import FullPageLoader from "../FullPageLoader";
import {logPublicationView} from "../../lib/log";

type Props = PropsWithChildren<{
    publication: Publication | undefined;
    reload: () => void;
    logPublicationView?: boolean;
}>;

export default function PublicationSecurityProxy({
    children,
    publication,
    reload,
    logPublicationView: log,
}: Props) {
    React.useEffect(() => {
        if (log && publication && publication.authorized) {
            logPublicationView(publication!.id);
        }
    }, [publication?.id, log]);

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

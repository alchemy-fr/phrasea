import {Share} from "../../types.ts";
import {routes} from "../../routes.ts";
import {getPath} from "@alchemy/navigation";

export function getShareUrl(s: Share) {
    return getPath(
        routes.share,
        {
            id: s.id,
            token: s.token,
        },
        {
            absoluteUrl: true,
        }
    );
}


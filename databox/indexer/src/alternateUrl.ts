import {config} from "./configLoader";
import {AlternateUrl} from "./databox/types";
import {Asset} from "./indexers";
import {IndexLocation} from "./types/config";

export function getAlternateUrls(
    {
        path,
        sourcePath,
    }: Asset,
    location: IndexLocation<any>
): AlternateUrl[] | undefined {
    const alternateUrls = location.alternateUrls || config.alternateUrls;

    if (alternateUrls) {
        const dict = {
            path,
            sourcePath,
        };

        return alternateUrls.map((c): AlternateUrl => {
            return {
                type: c.name,
                url: c.pathPattern.replace(/\${(.+)}/g, (m, m1) => {
                    return dict[m1];
                }),
            }
        });
    }

    return;
}

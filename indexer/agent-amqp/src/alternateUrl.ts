import {config} from "./configLoader";
import {AlternateUrl} from "./lib/databox/types";

export function getAlternateUrls(path: string): AlternateUrl[] | undefined
{
    if (config.alternateUrls) {
        const dict = {
            path,
        };

        return config.alternateUrls.map((c): AlternateUrl => {
            return {
                type: c.name,
                url: c.pathPattern.replace(/\${(.+)}/g, (m) => {
                    return dict[m];
                }),
            }
        });
    }

    return;
}

import {AssetServerFactory} from "../../server";
import {PhraseanetConfig} from "./types";

export const phraseanetAssetServerFactory: AssetServerFactory<PhraseanetConfig> = function () {
    return async (_path, res) => {
        res.redirect(307, 'http://localhost');
    }
}

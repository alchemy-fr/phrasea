import {Asset} from "../../../types.ts";
import {FunctionComponent} from "react";

export type BuiltInRenderProps = {
    asset: Asset;
}

export type BuiltInRenderComponent = FunctionComponent<BuiltInRenderProps>;

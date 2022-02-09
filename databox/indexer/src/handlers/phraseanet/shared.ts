import {Asset} from "../../indexers";
import {AttributeInput} from "../../databox/types";

export function createAsset(
    id: string,
    title: string,
    publicUrl: string,
    attributes: AttributeInput[]
): Asset {
    return {
        key: id,
        path: title,
        publicUrl,
        attributes,
    };
}

export const attributeTypesEquivalence = {
    string: 'text',
};

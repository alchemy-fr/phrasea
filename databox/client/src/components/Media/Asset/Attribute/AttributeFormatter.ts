import {AttributeType} from "../../../../api/attributes";
import {getAttributeType} from "./types";
import {AttributeFormat} from "./types/types";

export function formatAttribute(type: AttributeType, value: any, format?: AttributeFormat): string | undefined {
    if (!value) {
        return;
    }

    const formatter = getAttributeType(type);

    return formatter.formatValueAsString({
        value,
        locale: undefined,
        multiple: false,
        highlight: undefined,
        format,
    });
}

import {AttributeType} from "../../../../api/attributes";
import {types} from "./types";
import {AttributeFormat} from "./types/types";

export function formatAttribute(type: AttributeType, value: any, format?: AttributeFormat): string | undefined {
    if (!value) {
        return;
    }

    const formatter = types[type] ?? types[AttributeType.Text];

    return new formatter().formatValueAsString({
        value,
        locale: undefined,
        multiple: false,
        highlight: undefined,
        format,
    });
}

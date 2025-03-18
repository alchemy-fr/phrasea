import {AQLValue} from "./aqlTypes.ts";
import {hasProp} from "../../../../lib/utils.ts";

export type AQLQuery = {
    id: string;
    query: string;
    disabled?: boolean;
    inversed?: boolean;
};

export type AQLQueries = AQLQuery[];

export function resolveValue(value: AQLValue): string {
    if (typeof value === 'object' && hasProp(value, 'literal')) {
        return value.literal;
    }

    return value.toString();
}

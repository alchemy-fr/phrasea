import {AttributeDefinitionIndex} from "../../../AttributeEditor/types.ts";
import {AQLField, AQLQueryAST} from "./aqlTypes.ts";
import {hasProp} from "../../../../lib/utils.ts";

export function validateQueryAST(query: AQLQueryAST, definitionsIndex: AttributeDefinitionIndex): void
{
    function visitNode(node: any): void {
        if (typeof node === 'object') {
            if (hasProp<AQLField>(node, 'field')) {
                const f = node.field;
                if (!definitionsIndex[f]) {
                    throw new Error(`Field "${f}" does not exist`);
                }

                return;
            }

            Object.keys(node).forEach(k => {
                if (Array.isArray(node[k])) {
                    node[k].map(visitNode);
                } else {
                    visitNode(node[k]);
                }
            });
        }
    }

    visitNode(query.expression);
}

import {parseAQL} from './aql/parser';
import {isCondition, isField} from './aql/types';

/**
 * Attribute filtered by a single-condition AQL query (`title = "x"` →
 * `title`), undefined for any other query.
 */
export function conditionField(query: string): string | undefined {
    const expression = parseAQL(query)?.expression;
    if (
        !expression ||
        !isCondition(expression) ||
        !isField(expression.leftOperand)
    ) {
        return undefined;
    }

    return expression.leftOperand.field;
}

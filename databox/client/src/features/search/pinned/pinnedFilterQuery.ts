import {quoteAQL} from '../searchState';
import {
    AQLCondition,
    AQLOperator,
    AQLQueryAST,
    isCondition,
    isField,
    isLiteral,
    isLogical,
} from '../aql/types';

/**
 * A pinned filter controls one condition, identified by its field so that a
 * new value replaces the former one instead of stacking another condition.
 */
export function pinnedConditionId(field: string): string {
    return `pin-${field}`;
}

/** The conditions of `ast` on `field` (top-level ones only) */
export function conditionsOn(
    field: string,
    ast: AQLQueryAST | undefined
): AQLCondition[] {
    if (!ast) {
        return [];
    }
    const expression = ast.expression;
    const list = isLogical(expression)
        ? expression.conditions.filter(isCondition)
        : [expression];

    return list.filter(
        (c): c is AQLCondition =>
            isCondition(c) &&
            isField(c.leftOperand) &&
            c.leftOperand.field === field
    );
}

export type DateRange = {from: string; to: string};

const pad = (n: number) => String(n).padStart(2, '0');

/** `YYYY-MM-DD` of a stored date: the local day of a date-time */
function toDay(literal: string, withTime: boolean): string {
    if (withTime) {
        const d = new Date(literal);
        if (!Number.isNaN(d.getTime())) {
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        }
    }
    const m = /^\d{4}-\d{2}-\d{2}/.exec(literal);

    return m ? m[0] : '';
}

/** Bound of a day: its first or last instant (local time) for a date-time */
function fromDay(day: string, end: boolean, withTime: boolean): string {
    if (!withTime) {
        return day;
    }

    return new Date(
        `${day}T${end ? '23:59:59.999' : '00:00:00.000'}`
    ).toISOString();
}

/**
 * The day range of a date filter: both days included, either one optional.
 * Undefined when no day is set.
 */
export function dateRangeQuery(
    field: string,
    {from, to}: DateRange,
    withTime: boolean
): string | undefined {
    const a = from ? quoteAQL(fromDay(from, false, withTime)) : undefined;
    const b = to ? quoteAQL(fromDay(to, true, withTime)) : undefined;
    if (a && b) {
        return `${field} BETWEEN ${a} AND ${b}`;
    }
    if (a) {
        return `${field} >= ${a}`;
    }
    if (b) {
        return `${field} <= ${b}`;
    }

    return undefined;
}

/** The day range of the condition a date filter produced */
export function dateRangeOf(
    field: string,
    ast: AQLQueryAST | undefined,
    withTime: boolean
): DateRange {
    const range: DateRange = {from: '', to: ''};
    const day = (v: unknown) =>
        isLiteral(v) ? toDay(v.literal, withTime) : '';

    for (const c of conditionsOn(field, ast)) {
        const right = c.rightOperand;
        switch (c.operator) {
            case AQLOperator.BETWEEN:
                if (Array.isArray(right)) {
                    range.from = day(right[0]);
                    range.to = day(right[1]);
                }
                break;
            case AQLOperator.GT:
            case AQLOperator.GTE:
                range.from = day(right);
                break;
            case AQLOperator.LT:
            case AQLOperator.LTE:
                range.to = day(right);
                break;
            case AQLOperator.EQ:
                range.from = range.to = day(right);
                break;
        }
    }

    return range;
}

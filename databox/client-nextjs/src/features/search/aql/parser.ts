/**
 * Hand-written recursive-descent parser for AQL.
 *
 * Grammar (informal):
 *   expression   := andExpr ( OR andExpr )*
 *   andExpr      := unary ( AND unary )*
 *   unary        := NOT unary | "(" expression ")" | criteria
 *   criteria     := field operatorClause
 *   operator     := [NOT] BETWEEN value AND value | IS MISSING | EXISTS
 *                 | [NOT] IN "(" value ("," value)* ")"
 *                 | WITHIN CIRCLE "(" v "," v "," v ")" | WITHIN RECTANGLE "(" v "," v "," v "," v ")"
 *                 | ( = | != | > | < | >= | <= | CONTAINS | DOES NOT CONTAIN | MATCHES
 *                   | DOES NOT MATCH | STARTS WITH | DOES NOT START WITH ) value
 *   value        := sum ; sum := product (("+"|"-") product)* ; product := atom (("*"|"/") atom)*
 *   atom         := number | string | true | false | null | entity | identifier "(" args ")" | field | "(" value ")"
 */

import {
    AQLCondition,
    AQLEntity,
    AQLExpression,
    AQLLogical,
    AQLOperator,
    AQLQueryAST,
    AQLValueExpr,
} from './types';

export class AQLSyntaxError extends Error {
    constructor(
        message: string,
        public readonly position: number
    ) {
        super(message);
        this.name = 'AQLSyntaxError';
    }
}

type TokenType =
    | 'number'
    | 'string'
    | 'identifier'
    | 'builtin'
    | 'entity'
    | 'punct'
    | 'op'
    | 'eof';

type Token = {type: TokenType; value: string; pos: number; extra?: AQLEntity};

const PUNCT = new Set(['(', ')', ',', '+', '-', '*', '/']);

function tokenize(input: string): Token[] {
    const tokens: Token[] = [];
    let i = 0;
    const n = input.length;

    while (i < n) {
        const c = input[i];
        if (/\s/.test(c)) {
            i++;
            continue;
        }
        const pos = i;

        // comparison operators
        if (c === '!' && input[i + 1] === '=') {
            tokens.push({type: 'op', value: '!=', pos});
            i += 2;
            continue;
        }
        if ((c === '>' || c === '<') && input[i + 1] === '=') {
            tokens.push({type: 'op', value: c + '=', pos});
            i += 2;
            continue;
        }
        if (c === '=' || c === '>' || c === '<') {
            tokens.push({type: 'op', value: c, pos});
            i++;
            continue;
        }
        if (PUNCT.has(c)) {
            tokens.push({type: 'punct', value: c, pos});
            i++;
            continue;
        }
        // quoted string
        if (c === '"' || c === "'") {
            const quote = c;
            let j = i + 1;
            let out = '';
            while (j < n && input[j] !== quote) {
                if (input[j] === '\\' && j + 1 < n) {
                    const next = input[j + 1];
                    out +=
                        next === quote || next === '\\' || next === '"'
                            ? next
                            : '\\' + next;
                    j += 2;
                } else {
                    out += input[j];
                    j++;
                }
            }
            if (j >= n) {
                throw new AQLSyntaxError('Unterminated string literal', pos);
            }
            tokens.push({type: 'string', value: out, pos});
            i = j + 1;
            continue;
        }
        // entity reference @<id:label>
        if (c === '@' && input[i + 1] === '<') {
            const end = input.indexOf('>', i);
            if (end === -1) {
                throw new AQLSyntaxError('Unterminated entity reference', pos);
            }
            const body = input.slice(i + 2, end);
            const sep = body.indexOf(':');
            if (sep === -1) {
                throw new AQLSyntaxError('Invalid entity reference', pos);
            }
            tokens.push({
                type: 'entity',
                value: body,
                pos,
                extra: {
                    type: 'entity',
                    id: body.slice(0, sep),
                    label: body.slice(sep + 1),
                },
            });
            i = end + 1;
            continue;
        }
        // built-in field
        if (c === '@') {
            const m = /^@[a-zA-Z_][a-zA-Z0-9_-]*/.exec(input.slice(i));
            if (!m) {
                throw new AQLSyntaxError('Invalid built-in field', pos);
            }
            tokens.push({type: 'builtin', value: m[0], pos});
            i += m[0].length;
            continue;
        }
        // number
        const num = /^-?\d+(\.\d+)?/.exec(input.slice(i));
        if (num && (c !== '-' || /\d/.test(input[i + 1] ?? ''))) {
            // a leading '-' is only part of the number when directly followed by a digit
            // and not preceded by a value (handled by the parser as binary minus otherwise)
            const prev = tokens[tokens.length - 1];
            const prevIsValue =
                prev &&
                (prev.type === 'number' ||
                    prev.type === 'string' ||
                    prev.type === 'identifier' ||
                    prev.type === 'builtin' ||
                    prev.type === 'entity' ||
                    (prev.type === 'punct' && prev.value === ')'));
            if (c === '-' && prevIsValue) {
                tokens.push({type: 'punct', value: '-', pos});
                i++;
                continue;
            }
            tokens.push({type: 'number', value: num[0], pos});
            i += num[0].length;
            continue;
        }
        // identifier
        const id = /^[a-zA-Z_][a-zA-Z0-9_-]*/.exec(input.slice(i));
        if (id) {
            tokens.push({type: 'identifier', value: id[0], pos});
            i += id[0].length;
            continue;
        }

        throw new AQLSyntaxError(`Unexpected character "${c}"`, pos);
    }
    tokens.push({type: 'eof', value: '', pos: n});

    return tokens;
}

class Parser {
    private i = 0;

    constructor(private readonly tokens: Token[]) {}

    private peek(offset = 0): Token {
        return this.tokens[Math.min(this.i + offset, this.tokens.length - 1)];
    }

    private next(): Token {
        return this.tokens[this.i++];
    }

    private isKeyword(token: Token, word: string): boolean {
        return (
            token.type === 'identifier' && token.value.toUpperCase() === word
        );
    }

    private acceptKeyword(word: string): boolean {
        if (this.isKeyword(this.peek(), word)) {
            this.i++;

            return true;
        }

        return false;
    }

    private expectKeyword(word: string): void {
        const t = this.next();
        if (!this.isKeyword(t, word)) {
            throw new AQLSyntaxError(`Expected "${word}"`, t.pos);
        }
    }

    private expectPunct(value: string): void {
        const t = this.next();
        if (t.type !== 'punct' || t.value !== value) {
            throw new AQLSyntaxError(`Expected "${value}"`, t.pos);
        }
    }

    private acceptPunct(value: string): boolean {
        const t = this.peek();
        if (t.type === 'punct' && t.value === value) {
            this.i++;

            return true;
        }

        return false;
    }

    parse(): AQLExpression {
        const expr = this.expression();
        const t = this.peek();
        if (t.type !== 'eof') {
            throw new AQLSyntaxError(`Unexpected token "${t.value}"`, t.pos);
        }

        return expr;
    }

    private expression(): AQLExpression {
        const conditions: AQLExpression[] = [this.andExpression()];
        while (this.acceptKeyword('OR')) {
            conditions.push(this.andExpression());
        }

        return conditions.length === 1
            ? conditions[0]
            : {operator: AQLLogical.OR, conditions};
    }

    private andExpression(): AQLExpression {
        const conditions: AQLExpression[] = [this.unary()];
        while (this.acceptKeyword('AND')) {
            conditions.push(this.unary());
        }

        return conditions.length === 1
            ? conditions[0]
            : {operator: AQLLogical.AND, conditions};
    }

    private unary(): AQLExpression {
        if (this.acceptKeyword('NOT')) {
            return {operator: AQLLogical.NOT, conditions: [this.unary()]};
        }
        if (this.acceptPunct('(')) {
            const expr = this.expression();
            this.expectPunct(')');

            return expr;
        }

        return this.criteria();
    }

    private criteria(): AQLCondition {
        const t = this.peek();
        if (t.type !== 'identifier' && t.type !== 'builtin') {
            throw new AQLSyntaxError('Expected a field name', t.pos);
        }
        this.i++;
        const leftOperand = {field: t.value};

        // NOT BETWEEN / NOT IN
        if (this.isKeyword(this.peek(), 'NOT')) {
            const after = this.peek(1);
            if (this.isKeyword(after, 'BETWEEN')) {
                this.i += 2;

                return {
                    leftOperand,
                    ...this.betweenOperands(AQLOperator.NOT_BETWEEN),
                };
            }
            if (this.isKeyword(after, 'IN')) {
                this.i += 2;

                return {leftOperand, ...this.inOperands(AQLOperator.NOT_IN)};
            }
            throw new AQLSyntaxError(
                'Expected BETWEEN or IN after NOT',
                after.pos
            );
        }
        if (this.acceptKeyword('BETWEEN')) {
            return {leftOperand, ...this.betweenOperands(AQLOperator.BETWEEN)};
        }
        if (this.acceptKeyword('IN')) {
            return {leftOperand, ...this.inOperands(AQLOperator.IN)};
        }
        if (this.acceptKeyword('IS')) {
            this.expectKeyword('MISSING');

            return {leftOperand, operator: AQLOperator.MISSING};
        }
        if (this.acceptKeyword('EXISTS')) {
            return {leftOperand, operator: AQLOperator.EXISTS};
        }
        if (this.acceptKeyword('WITHIN')) {
            if (this.acceptKeyword('CIRCLE')) {
                return {
                    leftOperand,
                    operator: AQLOperator.WITHIN_CIRCLE,
                    rightOperand: this.argList(3),
                };
            }
            if (this.acceptKeyword('RECTANGLE')) {
                return {
                    leftOperand,
                    operator: AQLOperator.WITHIN_RECTANGLE,
                    rightOperand: this.argList(4),
                };
            }
            throw new AQLSyntaxError(
                'Expected CIRCLE or RECTANGLE',
                this.peek().pos
            );
        }
        if (this.acceptKeyword('CONTAINS')) {
            return {
                leftOperand,
                operator: AQLOperator.CONTAINS,
                rightOperand: this.value(),
            };
        }
        if (this.acceptKeyword('MATCHES')) {
            return {
                leftOperand,
                operator: AQLOperator.MATCHES,
                rightOperand: this.value(),
            };
        }
        if (this.acceptKeyword('STARTS')) {
            this.expectKeyword('WITH');

            return {
                leftOperand,
                operator: AQLOperator.STARTS_WITH,
                rightOperand: this.value(),
            };
        }
        if (this.acceptKeyword('DOES')) {
            this.expectKeyword('NOT');
            if (this.acceptKeyword('CONTAIN')) {
                return {
                    leftOperand,
                    operator: AQLOperator.NOT_CONTAINS,
                    rightOperand: this.value(),
                };
            }
            if (this.acceptKeyword('MATCH')) {
                return {
                    leftOperand,
                    operator: AQLOperator.NOT_MATCHES,
                    rightOperand: this.value(),
                };
            }
            if (this.acceptKeyword('START')) {
                this.expectKeyword('WITH');

                return {
                    leftOperand,
                    operator: AQLOperator.NOT_STARTS_WITH,
                    rightOperand: this.value(),
                };
            }
            throw new AQLSyntaxError(
                'Expected CONTAIN, MATCH or START WITH',
                this.peek().pos
            );
        }
        const op = this.peek();
        if (op.type === 'op') {
            this.i++;

            return {
                leftOperand,
                operator: op.value as AQLOperator,
                rightOperand: this.value(),
            };
        }

        throw new AQLSyntaxError(
            `Expected an operator after "${t.value}"`,
            op.pos
        );
    }

    private betweenOperands(
        operator: AQLOperator
    ): Pick<AQLCondition, 'operator' | 'rightOperand'> {
        const a = this.value();
        this.expectKeyword('AND');
        const b = this.value();

        return {operator, rightOperand: [a, b]};
    }

    private inOperands(
        operator: AQLOperator
    ): Pick<AQLCondition, 'operator' | 'rightOperand'> {
        this.expectPunct('(');
        const values: AQLValueExpr[] = [this.value()];
        while (this.acceptPunct(',')) {
            values.push(this.value());
        }
        this.expectPunct(')');

        return {operator, rightOperand: values};
    }

    private argList(count: number): AQLValueExpr[] {
        this.expectPunct('(');
        const values: AQLValueExpr[] = [];
        for (let k = 0; k < count; k++) {
            if (k > 0) {
                this.expectPunct(',');
            }
            values.push(this.value());
        }
        this.expectPunct(')');

        return values;
    }

    // value expressions ------------------------------------------------------

    private value(): AQLValueExpr {
        let left = this.product();
        while (true) {
            const t = this.peek();
            if (t.type === 'punct' && (t.value === '+' || t.value === '-')) {
                this.i++;
                left = {
                    type: 'value_expression',
                    operator: t.value,
                    leftOperand: left,
                    rightOperand: this.product(),
                };
            } else {
                return left;
            }
        }
    }

    private product(): AQLValueExpr {
        let left = this.atom();
        while (true) {
            const t = this.peek();
            if (t.type === 'punct' && (t.value === '*' || t.value === '/')) {
                this.i++;
                left = {
                    type: 'value_expression',
                    operator: t.value,
                    leftOperand: left,
                    rightOperand: this.atom(),
                };
            } else {
                return left;
            }
        }
    }

    private atom(): AQLValueExpr {
        const t = this.next();
        switch (t.type) {
            case 'number':
                return parseFloat(t.value);
            case 'string':
                return {literal: t.value};
            case 'entity':
                return t.extra!;
            case 'builtin':
                return {field: t.value};
            case 'punct':
                if (t.value === '(') {
                    const expression = this.value();
                    this.expectPunct(')');

                    return {type: 'parentheses', expression};
                }
                break;
            case 'identifier': {
                const lower = t.value.toLowerCase();
                if (lower === 'true') return true;
                if (lower === 'false') return false;
                if (lower === 'null') return null;
                if (this.peek().type === 'punct' && this.peek().value === '(') {
                    this.i++;
                    const args: AQLValueExpr[] = [];
                    if (!this.acceptPunct(')')) {
                        args.push(this.value());
                        while (this.acceptPunct(',')) {
                            args.push(this.value());
                        }
                        this.expectPunct(')');
                    }

                    return {
                        type: 'function_call',
                        function: t.value,
                        arguments: args,
                    };
                }

                return {field: t.value};
            }
            default:
                break;
        }

        throw new AQLSyntaxError(
            `Unexpected token "${t.value || 'end of input'}"`,
            t.pos
        );
    }
}

/**
 * Parses an AQL string. Throws {@link AQLSyntaxError} when `throwOnError` is
 * set, otherwise returns undefined on invalid input.
 */
export function parseAQL(
    query: string,
    throwOnError = false
): AQLQueryAST | undefined {
    try {
        const trimmed = query.trim();
        if (!trimmed) {
            throw new AQLSyntaxError('Empty query', 0);
        }
        const expression = new Parser(tokenize(trimmed)).parse();

        return {expression};
    } catch (e) {
        if (throwOnError) {
            throw e;
        }

        return undefined;
    }
}

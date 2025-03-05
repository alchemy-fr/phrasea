import nearley from "nearley";
import grammar from "./grammar.js";

type Condition = {
    field: string;
    value: string;
    operator: string;
}

type Expression = {
    operator?: 'AND' | 'OR';
    conditions: Condition[];
}

type Query = {
    expression: Expression;
}

export function parseAQLQuery(queryString: string): Query {
    const parser = new nearley.Parser(nearley.Grammar.fromCompiled(grammar));

    parser.feed(queryString);
    console.log('parser.results', parser.results);

    return {
        expression: parser.results[0],
    }
}

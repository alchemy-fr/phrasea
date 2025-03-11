import nearley from "nearley";
import grammar from "./grammar.ts";

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

export function parseAQLQuery(queryString: string): Query | undefined {
    const parser = new nearley.Parser(nearley.Grammar.fromCompiled(grammar),
        { keepHistory: true }
    );

    try {
        parser.feed(queryString);
    } catch (error) {
        console.error('error', error);

        return;
    }

    return {
        expression: parser.results[0],
    }
}

export enum InternalKey {
    Workspace = 'workspace',
    Collection = 'collection',
    CreatedAt = 'createdAt',
    Score = 'score',
}

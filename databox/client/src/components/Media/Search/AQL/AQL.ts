import nearley from "nearley";
import grammar from "./grammar.ts";
import {AQLQueryAST} from "./aqlTypes.ts";

export function parseAQLQuery(queryString: string): AQLQueryAST | undefined {
    const parser = new nearley.Parser(nearley.Grammar.fromCompiled(grammar),
        { keepHistory: true }
    );

    try {
        parser.feed(queryString.trim());
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

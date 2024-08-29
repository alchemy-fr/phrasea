import {createSkipArgsConstraint, createSkipFirstArgConstraint, MatcherRule} from "../Rules/rules";
import {OneOfNodeTypeRuleMatcher} from "../Rules/ruleMatchers";
import {SyntaxKind} from "ts-morph";

export const coreRules: MatcherRule[] = [
    new MatcherRule(
        "Skip key in property assignment",
        new OneOfNodeTypeRuleMatcher([
            SyntaxKind.PropertyAssignment,
        ]),
        [createSkipFirstArgConstraint()]
    ),
    new MatcherRule(
        "Skip switch clause",
        new OneOfNodeTypeRuleMatcher([
            SyntaxKind.SwitchStatement,
        ]),
        [createSkipArgsConstraint([2])]
    ),
    new MatcherRule(
        "Skip case clause",
        new OneOfNodeTypeRuleMatcher([
            SyntaxKind.CaseClause,
        ]),
        [createSkipArgsConstraint([1])]
    ),
    new MatcherRule(
        "Skip unwanted nodes",
        new OneOfNodeTypeRuleMatcher([
            SyntaxKind.TypeReference,
            SyntaxKind.IndexedAccessType,
            SyntaxKind.BinaryExpression,
            SyntaxKind.ElementAccessExpression,
        ]),
    ),
];

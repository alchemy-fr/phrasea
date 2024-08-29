import {createSkipFirstArgConstraint, MatcherRule} from "../Rules/rules";
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
        "Skip unwanted nodes",
        new OneOfNodeTypeRuleMatcher([
            SyntaxKind.TypeReference,
            SyntaxKind.IndexedAccessType,
            SyntaxKind.BinaryExpression,
            SyntaxKind.ElementAccessExpression,
        ]),
    ),
];

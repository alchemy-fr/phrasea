import {MatcherRule} from "../Rules/rules";
import {OneOfNodeTypeRuleMatcher} from "../Rules/ruleMatchers";
import {SyntaxKind} from "ts-morph";
import {RuleConstraintType, SkipArgumentsRuleConstraint} from "../types";

export const coreRules: MatcherRule[] = [
    new MatcherRule(
        "Skip key in property assignment",
        new OneOfNodeTypeRuleMatcher([
            SyntaxKind.PropertyAssignment,
        ]),
        [{
            type: RuleConstraintType.SkipArguments,
            arguments: [0],
        } as SkipArgumentsRuleConstraint]
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

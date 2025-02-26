import {
    createSkipChildrenConstraint,
    createSkipFirstChildConstraint,
    identifierRegex,
    MatcherRule,
} from '../Rules/rules';
import {
    LiteralInBinaryExpressionRuleMatcher,
    OneOfNodeTypeRuleMatcher,
} from '../Rules/ruleMatchers';
import {SyntaxKind} from 'ts-morph';
import {Rule} from '../types';

export const coreRules: Rule[] = [
    new MatcherRule(
        'Skip key in property assignment',
        new OneOfNodeTypeRuleMatcher([SyntaxKind.PropertyAssignment]),
        [createSkipFirstChildConstraint()]
    ),
    new MatcherRule(
        'Skip switch clause',
        new OneOfNodeTypeRuleMatcher([SyntaxKind.SwitchStatement]),
        [createSkipChildrenConstraint([2])]
    ),
    new MatcherRule(
        'Skip case clause',
        new OneOfNodeTypeRuleMatcher([SyntaxKind.CaseClause]),
        [createSkipChildrenConstraint([1])]
    ),
    new MatcherRule(
        'Skip unwanted nodes',
        new OneOfNodeTypeRuleMatcher([
            SyntaxKind.TypeReference,
            SyntaxKind.IndexedAccessType,
            SyntaxKind.ElementAccessExpression,
        ])
    ),
    new MatcherRule(
        'Skip typeof and keys comparison',
        new LiteralInBinaryExpressionRuleMatcher([identifierRegex])
    ),
];

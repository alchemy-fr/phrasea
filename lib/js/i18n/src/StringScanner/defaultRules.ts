import {MatcherRule,} from "./Rules/rules";
import {Rule, RuleConstraintType, SkipArgumentsRuleConstraint} from "./types";
import {SyntaxKind} from "ts-morph";
import {
    FunctionCallNameRuleMatcher,
    JsxAttributeOrPropertyNameRuleMatcher,
    JsxElementNameRuleMatcher,
    LiteralValueRuleMatcher,
    OneOfNodeTypeRuleMatcher,
    PropertyNameRuleMatcher,
    VariableNameRuleMatcher
} from "./Rules/ruleMatchers";
import {muiRules} from "./ruleSet/muiRules";

const coreRules: MatcherRule[] = [
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

export const defaultRules: Rule[] = [
    ...coreRules,
    ...muiRules,
    new MatcherRule(
        "Skip Trans JSX element",
        new JsxElementNameRuleMatcher([/^Trans$/]),
    ),
    new MatcherRule(
        "Skip unwanted strings",
        new LiteralValueRuleMatcher([
            /^[()\[\]\-|/+•#%:]$/, // single punctionation
            /^#(\d{3}|\d{6})$/, // color
        ]),
    ),
    new MatcherRule(
        "Skip unwanted variables",
        new VariableNameRuleMatcher([
            /^(data|d)$/,
            /^(aria|class|anchor)/,
            /(Id|Sx)$/,
            /Class(es|Name)?$/,
            /^class/,
        ]),
    ),
    new MatcherRule("Skip unwanted functions", new FunctionCallNameRuleMatcher([
            /^(debug|log)/,
            /^t$/,
            /^(watch|register)$/,
            /^NumberFormat$/,
            /^useState$/,
            /^hasOwnProperty$/,
        ]),
    ),
    new MatcherRule(
        "Skip first arguments of functions",
        new FunctionCallNameRuleMatcher([
            /^has/,
            /^(append|add)/,
        ]),
        [{
            type: RuleConstraintType.SkipArguments,
            arguments: [0],
        } as SkipArgumentsRuleConstraint]
    ),

    new MatcherRule(
        "Skip unwanted attributes",
        new JsxAttributeOrPropertyNameRuleMatcher([
            /^(aria|class|anchor)/,
            /Class(Name)?$/,
            /(Id|Sx|Ur[il])$/,
            /^(min|max)(Width|Height)$/,
            /^(field|placement|sx|key|color|role|loadingPosition|height|width|style|modifiers|transform|direction|orientation|alignItems|valueLabelDisplay|component|mouseEvent|id|position|origin|padding|transition|background)$/,
            /accept/i,
            /ur[il]/i,
        ]),
    ),

    new MatcherRule(
        "Skip unwanted object properties",
        new PropertyNameRuleMatcher([
            /content-type/i,
            /accept/i,
            /url/i,
        ]),
    ),
];

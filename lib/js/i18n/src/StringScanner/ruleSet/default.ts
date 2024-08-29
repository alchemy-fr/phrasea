import {ChainedMatcherRule, MatcherRule,} from "../Rules/rules";
import {Rule, RuleConstraintType, SkipArgumentsRuleConstraint} from "../types";
import {
    FunctionCallNameRuleMatcher,
    JsxAttributeOrPropertyNameRuleMatcher,
    JsxElementNameRuleMatcher,
    LiteralValueRuleMatcher,
    PropertyNameRuleMatcher,
    VariableNameRuleMatcher, VariableOrJsxAttributeOrPropertyNameRuleMatcher
} from "../Rules/ruleMatchers";
import {muiRules} from "./mui";
import {coreRules} from "./core";
import {styleRules} from "./style";

export const defaultRules: Rule[] = [
    ...coreRules,
    ...muiRules,
    ...styleRules,
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
        "Skip unwanted variables or attributes",
        new VariableOrJsxAttributeOrPropertyNameRuleMatcher([
            /^(data|d)$/,
            /Class(es|Name)?$/,
            /^class/,
            /^(aria|class|anchor)/,
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
    new ChainedMatcherRule(
        "API calls",
        [
            new FunctionCallNameRuleMatcher([
                /^(get|post|put|delete|patch)$/,
            ]),
            new LiteralValueRuleMatcher([
                /^\//,
                /[a-z-]+/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "Collection calls",
        [
            new FunctionCallNameRuleMatcher([
                /^(get|has)$/,
                /^(add|append|has|remove|delete)/,
            ]),
            new LiteralValueRuleMatcher([
                /^[a-z-]+$/,
            ])
        ],
    ),
    new ChainedMatcherRule(
        "Type or Key keyword",
        [
            new VariableOrJsxAttributeOrPropertyNameRuleMatcher([
                /^(type|key)$/,
                /^(add|append|has|remove|delete)/,
            ]),
            new LiteralValueRuleMatcher([
                /^[a-z-]+$/,
            ])
        ],
    ),
];

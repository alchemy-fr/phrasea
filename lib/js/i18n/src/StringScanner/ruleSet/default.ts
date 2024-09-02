import {ChainedMatcherRule, identifierRegex, MatcherRule,} from "../Rules/rules";
import {Rule, RuleConstraintType, SkipChildrenRuleConstraint} from "../types";
import {
    ClassInstantiationNameRuleMatcher,
    FunctionCallNameRuleMatcher,
    JsxElementNameRuleMatcher,
    LiteralValueRuleMatcher,
    PropertyNameRuleMatcher,
    AnyNameRuleMatcher
} from "../Rules/ruleMatchers";
import {muiRules} from "./mui";
import {coreRules} from "./core";
import {styleRules} from "./style";
import {phraseaRules} from "./phrasea";

export const defaultRules: Rule[] = [
    ...phraseaRules,
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
            /^[()\[\]\-|/+•#%:]$/, // single punctuation
            /^#([A-F\d]{3}|[A-F\d]{6})$/i, // color
            /^\d+$/, // number
            /^[,;]\s*$/, // separator
        ]),
    ),
    new MatcherRule("Skip unwanted functions", new FunctionCallNameRuleMatcher([
            /^(debug|log)/,
            /^t$/,
            /^(watch|register)$/,
            /^useState$/,
            /^hasOwnProperty$/,
        ]),
    ),
    new MatcherRule("Skip unwanted functions", new ClassInstantiationNameRuleMatcher([
            /^Intl.NumberFormat/,
        ]),
    ),
    new MatcherRule(
        "Skip first arguments of functions",
        new FunctionCallNameRuleMatcher([
            /^has/,
            /^(append|add)/,
        ]),
        [{
            type: RuleConstraintType.skipChildren,
            positions: [0],
        } as SkipChildrenRuleConstraint]
    ),
    new MatcherRule(
        "Skip unwanted variables or attributes",
        new AnyNameRuleMatcher([
            /^(data|d)$/,
            /Class(es|Name)?$/,
            /^class/,
            /^aspectRatio$/,
            /^(aria|class|anchor)/,
            /(Id|Sx|Ur[il])$/,
            /^(min|max)(Width|Height)$/i,
            /^(field|placement|sx|key|color|role|loadingPosition|height|width|style|modifiers|transform|direction|orientation|alignItems|valueLabelDisplay|component|mouseEvent|id|position|origin|padding|transition|background)$/,
            /accept/i,
            /ur[il]/i,
            /crossOrigin/i,
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
    new MatcherRule(
        "querySelector",
        new FunctionCallNameRuleMatcher([
            /^querySelector/i,
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
                identifierRegex,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "Collection calls",
        [
            new FunctionCallNameRuleMatcher([
                /^(get|has|set|update)/,
                /^(add|append|has|remove|delete)/,
            ]),
            new LiteralValueRuleMatcher([
                identifierRegex,
            ])
        ],
    ),
    new ChainedMatcherRule(
        "Type or Key keyword",
        [
            new AnyNameRuleMatcher([
                /(type|key|value)$/i,
                /^(add|append|has|remove|delete)/,
            ]),
            new LiteralValueRuleMatcher([
                identifierRegex,
            ])
        ],
    ),
];

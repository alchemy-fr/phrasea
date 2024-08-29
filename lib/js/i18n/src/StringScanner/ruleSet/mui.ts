import {Rule} from "../types";
import {ChainedMatcherRule, MatcherRule} from "../Rules/rules";
import {
    FunctionDeclarationNameRuleMatcher,
    JsxAttributeOrPropertyNameRuleMatcher,
    JsxElementNameRuleMatcher,
    LiteralValueRuleMatcher,
    VariableNameRuleMatcher,
    VariableOrJsxAttributeOrPropertyNameRuleMatcher
} from "../Rules/ruleMatchers";

export const muiRules: Rule[] = [
    new ChainedMatcherRule(
        "MUI Button props",
        [
            new JsxElementNameRuleMatcher([
                /(Icon|Loading)?Button/,
                /^Skeleton$/,
            ]),
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^(variant)$/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Variant rule",
        [
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^variant$/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(h[1-6]|body\d?|contained|outlined|text|standard|dense)$/i,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Colors rule",
        [
            new VariableOrJsxAttributeOrPropertyNameRuleMatcher([
                /(color|severity)/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(primary|secondary|default|warning|error|info|success)$/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Colors rule",
        [
            new VariableNameRuleMatcher([
                /(color|severity)/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(primary|secondary|default|warning|error|info|success)$/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Sizes rule",
        [
            new JsxAttributeOrPropertyNameRuleMatcher([
                /size/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(small|large|xl|md|sm|xs)$/,
            ])
        ]
    ),
    new MatcherRule(
        "MUI attributes",
        new JsxAttributeOrPropertyNameRuleMatcher([
            /wrap/i,
        ]),
    ),
    new MatcherRule(
        "SX builder",
        new FunctionDeclarationNameRuleMatcher([
            /Sx$/,
        ]),
    ),
];

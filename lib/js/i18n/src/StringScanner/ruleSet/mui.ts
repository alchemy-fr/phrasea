import {Rule} from "../types";
import {ChainedMatcherRule, MatcherRule} from "../Rules/rules";
import {
    FunctionDeclarationNameRuleMatcher,
    JsxAttributeOrPropertyNameRuleMatcher,
    JsxElementNameRuleMatcher,
    LiteralValueRuleMatcher,
    VariableNameRuleMatcher,
    AnyNameRuleMatcher
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
        "MUI Checkbox",
        [
            new JsxElementNameRuleMatcher([
                /^Checkbox$/,
            ]),
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^edge$/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Collapse",
        [
            new JsxElementNameRuleMatcher([
                /^Collapse$/,
            ]),
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^timeout$/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Variant",
        [
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^variant$/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(contained|outlined|text|standard|dense|scrollable|auto|buffer|indeterminate)$/i,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI typography",
        [
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^(variant|typography)$/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(h[1-6]|body\d?|subtitle\d?|caption)$/i,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Placement",
        [
            new AnyNameRuleMatcher([
                /placement$/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(end|start|top|bottom|left|right)$/i,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Colors rule",
        [
            new AnyNameRuleMatcher([
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
                /^(medium|small|large|xl|md|sm|xs)$/,
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

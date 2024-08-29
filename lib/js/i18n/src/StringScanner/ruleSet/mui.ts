import {Rule} from "../types";
import {ChainedMatcherRule} from "../Rules/rules";
import {
    JsxAttributeNameRuleMatcher,
    JsxAttributeOrPropertyNameRuleMatcher,
    JsxElementNameRuleMatcher, LiteralValueRuleMatcher
} from "../Rules/ruleMatchers";

export const muiRules: Rule[] = [
    new ChainedMatcherRule(
        "MUI Button props",
        [
            new JsxElementNameRuleMatcher([
                /(Icon|Loading)?Button/,
            ]),
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^(variant)$/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Variant rule",
        [
            new JsxAttributeNameRuleMatcher([
                /^variant$/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(h[1-6]|body\d?)$/i,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Colors rule",
        [
            new JsxAttributeNameRuleMatcher([
                /color/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(primary|secondary|default|warning|error|info|success)$/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Sizes rule",
        [
            new JsxAttributeNameRuleMatcher([
                /size/i,
            ]),
            new LiteralValueRuleMatcher([
                /^(small|large|xl|md|sm|xs)$/,
            ])
        ]
    ),
];

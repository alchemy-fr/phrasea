import {Rule} from "../types";
import {ChainedMatcherRule} from "../Rules/rules";
import {
    JsxAttributeNameRuleMatcher,
    JsxAttributeOrPropertyNameRuleMatcher,
    JsxElementNameRuleMatcher
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
        "MUI Colors rule",
        [
            new JsxAttributeNameRuleMatcher([
                /color/i,
            ]),
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^(primary|secondary|default|warning|error|info|success)$/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Colors rule",
        [
            new JsxAttributeNameRuleMatcher([
                /variant/i,
            ]),
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^(h[1-6]|body\d?)$/,
            ])
        ]
    ),
    new ChainedMatcherRule(
        "MUI Sizes rule",
        [
            new JsxAttributeNameRuleMatcher([
                /size/i,
            ]),
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^(small|large|xl|md|sm|xs)$/,
            ])
        ]
    ),
];

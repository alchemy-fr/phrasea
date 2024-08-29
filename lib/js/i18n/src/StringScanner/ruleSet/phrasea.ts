import {Rule} from "../types";
import {ChainedMatcherRule, MatcherRule} from "../Rules/rules";
import {
    FunctionCallNameRuleMatcher, JsxAttributeNameRuleMatcher,
    JsxAttributeOrPropertyNameRuleMatcher, JsxElementNameRuleMatcher, LiteralValueRuleMatcher,
    VariableOrJsxAttributeOrPropertyNameRuleMatcher
} from "../Rules/ruleMatchers";

export const phraseaRules: Rule[] = [
    new ChainedMatcherRule(
        "Actions",
        [
            new VariableOrJsxAttributeOrPropertyNameRuleMatcher([
                /action/,
            ]),
            new JsxAttributeOrPropertyNameRuleMatcher([
                /^name$/,
            ]),
        ]
    ),
    new ChainedMatcherRule(
        "Form names",
        [
            new JsxElementNameRuleMatcher([
                /(Widget|Field)$/,
            ]),
            new JsxAttributeNameRuleMatcher([
                /^name$/,
            ]),
        ]
    ),
    new ChainedMatcherRule(
        "Form register",
        [
            new JsxElementNameRuleMatcher([
                /(Widget|Field)$/,
            ]),
            new FunctionCallNameRuleMatcher([
                /^register$/,
            ]),
        ]
    ),
    new ChainedMatcherRule(
        "Tabs",
        [
            new VariableOrJsxAttributeOrPropertyNameRuleMatcher([
                /^tab$/,
            ]),
            new LiteralValueRuleMatcher([
                /^[a-z-_\d]+$/,
            ]),
        ]
    ),
];

import {Rule} from "../types";
import {
    ChainedMatcherRule,
    createSkipFirstArgumentConstraint,
    MatcherRule
} from "../Rules/rules";
import {
    ClassInstantiationNameRuleMatcher, FullFunctionCallNameRuleMatcher,
    FunctionCallNameRuleMatcher,
    JsxAttributeNameRuleMatcher,
    JsxAttributeOrPropertyNameRuleMatcher,
    JsxElementNameRuleMatcher,
    LiteralValueRuleMatcher,
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
                /^Controller$/,
                /.+Select$/,
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
    new ChainedMatcherRule(
        "Run actions",
        [
            new FunctionCallNameRuleMatcher([
                /^run/,
            ]),
            new LiteralValueRuleMatcher([
                /^[a-z-_\d]+$/,
            ]),
        ]
    ),
    new ChainedMatcherRule(
        "Keys",
        [
            new FunctionCallNameRuleMatcher([
                /^includes$/,
            ]),
            new LiteralValueRuleMatcher([
                /^[^A-Z]+$/,
            ]),
        ]
    ),
    new MatcherRule('updatePreference', new FunctionCallNameRuleMatcher([
            /^updatePreference$/,
        ]),
        [createSkipFirstArgumentConstraint()],
    ),
    new MatcherRule(
        "Errors",
        new ClassInstantiationNameRuleMatcher([
            /^(Error)$/,
        ]),
    ),
    new MatcherRule(
        "console.x",
        new FullFunctionCallNameRuleMatcher([
            /^console\.(error|info|debug|trace)$/,
        ]),
    ),
    new MatcherRule(
        "Moment",
        new FullFunctionCallNameRuleMatcher([
            /^(m(oment)?).format$/,
        ]),
    ),
    new MatcherRule(
        "Unwanted functions",
        new FunctionCallNameRuleMatcher([
            /^(toggleAttrFilter|useNavigateToOverlay)$/,
        ]),
    ),
    new MatcherRule(
        "Moment",
        new VariableOrJsxAttributeOrPropertyNameRuleMatcher([
            /^theme$/,
        ]),
    ),
];

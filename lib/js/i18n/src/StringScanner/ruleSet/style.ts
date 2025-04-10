import {Rule} from '../types';
import {ChainedMatcherRule, MatcherRule} from '../Rules/rules';
import {
    FunctionCallNameRuleMatcher,
    FunctionDeclarationNameRuleMatcher,
    JsxAttributeNameRuleMatcher,
    JsxAttributeOrPropertyNameRuleMatcher,
    LiteralValueRuleMatcher,
} from '../Rules/ruleMatchers';

export const styleRules: Rule[] = [
    new ChainedMatcherRule('Styles', [
        new JsxAttributeOrPropertyNameRuleMatcher([/(align|position)/i]),
        new LiteralValueRuleMatcher([/^(left|right|bottom|top)$/i]),
    ]),
    new ChainedMatcherRule('Form', [
        new JsxAttributeOrPropertyNameRuleMatcher([/^(type)$/i]),
        new LiteralValueRuleMatcher([/^(button|submit|reset|search)$/i]),
    ]),
    new ChainedMatcherRule('Scroll', [
        new JsxAttributeOrPropertyNameRuleMatcher([/(scroll)/i]),
        new LiteralValueRuleMatcher([/^(auto|scrollable)$/i]),
    ]),
    new MatcherRule(
        'DOM',
        new FunctionCallNameRuleMatcher([/^(createElement)$/i])
    ),
    new MatcherRule(
        'Style JSX Attribute',
        new JsxAttributeNameRuleMatcher([/^(styles?)$/i])
    ),
    new MatcherRule(
        'Style builder',
        new FunctionDeclarationNameRuleMatcher([/Styles?$/])
    ),
];

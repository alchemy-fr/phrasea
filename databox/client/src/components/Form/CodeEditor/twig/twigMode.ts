import ace from 'ace-builds';
import 'ace-builds/src-noconflict/mode-html';

const HtmlHighlightRules = ace.require(
    'ace/mode/html_highlight_rules'
).HtmlHighlightRules;
const HtmlMode = ace.require('ace/mode/html').Mode;

class TwigHighlightRules extends HtmlHighlightRules {
    constructor() {
        super();

        // repeat the HTML rules (like PHP does)
        // HtmlHighlightRules.call(this);

        // capture all of the twig keywords
        let tags =
            'autoescape|block|do|embed|extends|filter|flush|for|from|if|import|include|macro|sandbox|set|spaceless|use|verbatim';
        tags += 'end' + tags.replace(/\|/g, '|end');
        const filters =
            'abs|batch|capitalize|convert_encoding|date|date_modify|default|e|escape|first|format|join|json_encode|keys|last|length|lower|merge|nl2br|number_format|raw|replace|reverse|slice|sort|split|striptags|title|trim|upper|url_encode';
        const functions =
            'attribute|block|constant|cycle|date|dump|include|parent|random|range|template_from_string';
        const tests =
            'constant|divisibleby|sameas|defined|empty|even|iterable|odd';
        const constants = 'null|none|true|false';

        const operators: any = {};
        operators.bitwise = 'b-and|b-xor|b-or';
        operators.comparison = 'in|is';
        operators.logical = 'and|or|not';

        const keywordMapper = this.createKeywordMapper(
            {
                'keyword.control.twig': tags,
                'support.function.twig': [filters, functions, tests].join('|'),
                'keyword.operator.bitwise.twig': operators.bitwise,
                'keyword.operator.comparison.twig': operators.comparison,
                'keyword.operator.logical.twig': operators.logical,
                'constant.language.twig': constants,
            },
            'identifier'
        );

        // regexp must not have capturing parentheses. Use (?:) instead.
        // regexps are ordered -> the first match is used

        // add twig start tags to the HTML start tags
        this.$rules.start.unshift(
            {
                token: 'variable.other.readwrite.local.twig',
                regex: '\\{\\{-?',
                next: 'twig-start',
            },
            {
                token: 'meta.tag.twig',
                regex: '\\{%-?',
                next: 'twig-start',
            },
            {
                token: 'comment.block.twig',
                regex: '\\{#-?',
                next: 'comment',
            }
        );

        // add twig closing comment to HTML comments
        this.$rules.comment.unshift({
            token: 'comment.block.twig',
            regex: '.*-?#\\}',
            next: 'start',
        });

        // Specific twig rules (heavily borrowed from Liquid, some from JavaScript)
        this.$rules['twig-start'] = [
            {
                token: 'variable.other.readwrite.local.twig',
                regex: '-?\\}\\}',
                next: 'start',
            },
            {
                token: 'meta.tag.twig',
                regex: '-?%\\}',
                next: 'start',
            },
            {
                token: 'string',
                regex: "'(?=.)",
                next: 'twig-qstring',
            },
            {
                token: 'string',
                regex: '"(?=.)',
                next: 'twig-qqstring',
            },
            {
                token: 'constant.numeric', // hex
                regex: '0[xX][0-9a-fA-F]+\\b',
            },
            {
                token: 'constant.numeric', // float
                regex: '[+-]?\\d+(?:(?:\\.\\d*)?(?:[eE][+-]?\\d+)?)?\\b',
            },
            {
                token: 'constant.language.boolean',
                regex: '(?:true|false)\\b',
            },
            {
                token: keywordMapper,
                regex: '[a-zA-Z_$][a-zA-Z0-9_$]*\\b',
            },
            {
                token: 'keyword.operator.assignment',
                regex: '=|~',
            },
            {
                token: 'keyword.operator.comparison',
                regex: '==|!=|<|>|>=|<=|===',
            },
            {
                token: 'keyword.operator.arithmetic',
                regex: '\\+|-|/|%|//|\\*|\\*\\*',
            },
            {
                token: 'keyword.operator.other',
                regex: '\\.\\.|\\|',
            },
            {
                token: 'punctuation.operator',
                regex: /\?|:|,|;|\./,
            },
            {
                token: 'paren.lparen',
                regex: /[[({]/,
            },
            {
                token: 'paren.rparen',
                regex: /[\])}]/,
            },
            {
                token: 'text',
                regex: '\\s+',
            },
        ];

        // Borrow quoted strings from JavaScript
        // TODO: is escapedRe appropriate for Twig?
        const escapedRe =
            '\\\\(?:x[0-9a-fA-F]{2}|' + // hex
            'u[0-9a-fA-F]{4}|' + // unicode
            '[0-2][0-7]{0,2}|' + // oct
            '3[0-6][0-7]?|' + // oct
            '37[0-7]?|' + // oct
            '[4-7][0-7]?|' + //oct
            '.)';

        this.$rules['twig-qqstring'] = [
            {
                token: 'constant.language.escape',
                regex: escapedRe,
            },
            {
                token: 'string',
                regex: '\\\\$',
                next: 'twig-qqstring',
            },
            {
                token: 'string',
                regex: '"|$',
                next: 'twig-start',
            },
            {
                token: 'string',
                regex: '.|\\w+|\\s+',
            },
        ];

        this.$rules['twig-qstring'] = [
            {
                token: 'constant.language.escape',
                regex: escapedRe,
            },
            {
                token: 'string',
                regex: '\\\\$',
                next: 'twig-qstring',
            },
            {
                token: 'string',
                regex: "'|$",
                next: 'twig-start',
            },
            {
                token: 'string',
                regex: '.|\\w+|\\s+',
            },
        ];
    }
}

export default class TwigMode extends HtmlMode {
    constructor() {
        super();
        this.HighlightRules = TwigHighlightRules;
    }

    path = 'ace/mode/twig';
}

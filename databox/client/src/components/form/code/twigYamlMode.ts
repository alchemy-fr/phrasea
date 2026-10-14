import type {Ace} from 'ace-builds';

type AceModule = typeof import('ace-builds');

export const TWIG_YAML_MODE = 'ace/mode/twig_yaml';

// `ace.require` is untyped: so are the classes built on what it returns
let TwigYamlMode: (new () => Ace.SyntaxMode) | undefined;

/**
 * The `twig_yaml` mode: YAML highlighting, with Twig tags (`{{ … }}`,
 * `{% … %}`, `{# … #}`) highlighted wherever they appear. Needs `mode-yaml`
 * to be loaded.
 */
export function createTwigYamlMode(ace: AceModule): Ace.SyntaxMode {
    if (!TwigYamlMode) {
        const {YamlHighlightRules} = ace.require(
            'ace/mode/yaml_highlight_rules'
        );
        const {Mode: YamlMode} = ace.require('ace/mode/yaml');

        class TwigYamlHighlightRules extends YamlHighlightRules {
            constructor() {
                super();
                addTwigRules(this);
            }
        }

        TwigYamlMode = class extends YamlMode {
            constructor() {
                super();
                this.HighlightRules = TwigYamlHighlightRules;
                this.$id = TWIG_YAML_MODE;
            }
        } as new () => Ace.SyntaxMode;
    }

    return new TwigYamlMode!();
}

const tags = (() => {
    const t =
        'autoescape|block|do|embed|extends|filter|flush|for|from|if|import|include|macro|sandbox|set|spaceless|use|verbatim|with|apply|else|elseif';

    return `${t}|end${t.replace(/\|/g, '|end')}`;
})();

// Ported from the Twig mode of the legacy client, on top of YAML
function addTwigRules(rules: any): void {
    const keywordMapper = rules.createKeywordMapper(
        {
            'keyword.control.twig': tags,
            'support.function.twig': [
                'abs|batch|capitalize|convert_encoding|date|date_modify|default|e|escape|first|format|join|json_encode|keys|last|length|lower|map|filter|merge|nl2br|number_format|raw|replace|reverse|slice|sort|split|striptags|title|trim|upper|url_encode',
                'attribute|block|constant|cycle|date|dump|include|parent|random|range|template_from_string',
                'constant|divisibleby|sameas|defined|empty|even|iterable|odd',
            ].join('|'),
            'keyword.operator.bitwise.twig': 'b-and|b-xor|b-or',
            'keyword.operator.comparison.twig': 'in|is',
            'keyword.operator.logical.twig': 'and|or|not',
            'constant.language.twig': 'null|none|true|false',
        },
        'identifier'
    );

    const openers = [
        {
            token: 'variable.other.readwrite.local.twig',
            regex: '\\{\\{-?',
            next: 'twig-start',
        },
        {token: 'meta.tag.twig', regex: '\\{%-?', next: 'twig-start'},
        {token: 'comment.block.twig', regex: '\\{#-?', next: 'twig-comment'},
    ];
    // Before the YAML rules: `{{` would otherwise read as a flow mapping
    rules.$rules.start.unshift(...openers);

    rules.$rules['twig-comment'] = [
        {token: 'comment.block.twig', regex: '.*?-?#\\}', next: 'start'},
        {defaultToken: 'comment.block.twig'},
    ];

    const escapedRe =
        '\\\\(?:x[0-9a-fA-F]{2}|u[0-9a-fA-F]{4}|[0-2][0-7]{0,2}|3[0-6][0-7]?|37[0-7]?|[4-7][0-7]?|.)';
    const quoted = (quote: string, state: string) => [
        {token: 'constant.language.escape', regex: escapedRe},
        {token: 'string', regex: '\\\\$', next: state},
        {token: 'string', regex: `${quote}|$`, next: 'twig-start'},
        {token: 'string', regex: '.|\\w+|\\s+'},
    ];

    rules.$rules['twig-start'] = [
        {
            token: 'variable.other.readwrite.local.twig',
            regex: '-?\\}\\}',
            next: 'start',
        },
        {token: 'meta.tag.twig', regex: '-?%\\}', next: 'start'},
        {token: 'string', regex: "'(?=.)", next: 'twig-qstring'},
        {token: 'string', regex: '"(?=.)', next: 'twig-qqstring'},
        {token: 'constant.numeric', regex: '0[xX][0-9a-fA-F]+\\b'},
        {
            token: 'constant.numeric',
            regex: '[+-]?\\d+(?:(?:\\.\\d*)?(?:[eE][+-]?\\d+)?)?\\b',
        },
        {token: 'constant.language.boolean', regex: '(?:true|false)\\b'},
        {token: keywordMapper, regex: '[a-zA-Z_$][a-zA-Z0-9_$]*\\b'},
        {token: 'keyword.operator.assignment', regex: '=|~'},
        {token: 'keyword.operator.comparison', regex: '==|!=|<|>|>=|<=|==='},
        {
            token: 'keyword.operator.arithmetic',
            regex: '\\+|-|/|%|//|\\*|\\*\\*',
        },
        {token: 'keyword.operator.other', regex: '\\.\\.|\\|'},
        {token: 'punctuation.operator', regex: /\?|:|,|;|\./},
        {token: 'paren.lparen', regex: /[[({]/},
        {token: 'paren.rparen', regex: /[\])}]/},
        {token: 'text', regex: '\\s+'},
    ];
    rules.$rules['twig-qqstring'] = quoted('"', 'twig-qqstring');
    rules.$rules['twig-qstring'] = quoted("'", 'twig-qstring');
    rules.normalizeRules();
}

/**
 * Completes the properties of the Twig context (`file.`, `asset.`,
 * `attr.<slug>`), in the `twig_yaml` mode only.
 */
export function twigCompleter(
    getAttributeSlugs: () => string[]
): Ace.Completer {
    return {
        id: 'twig',
        getCompletions: (_editor, session, pos, _prefix, callback) => {
            if ((session.getMode() as any)?.$id !== TWIG_YAML_MODE) {
                return callback(null, []);
            }
            const props: Record<string, string[]> = {
                file: ['getFilename()', 'getId()', 'getSize()'],
                attr: ['name', ...getAttributeSlugs()],
                asset: ['getId()', 'getSource()', 'getCollections()'],
            };

            let token = session.getTokenAt(pos.row, pos.column);
            if (token?.type === 'identifier' && token.start !== undefined) {
                token = session.getTokenAt(pos.row, token.start);
            }
            if (token?.type === 'punctuation.operator' && token.value === '.') {
                token = session.getTokenAt(pos.row, pos.column - 2);
            }
            if (!token || token.type !== 'identifier') {
                return callback(null, []);
            }
            const identifier = token.value;

            callback(
                null,
                Object.entries(props).flatMap(([key, values]) =>
                    identifier.endsWith(key)
                        ? values.map(value => ({
                              value,
                              meta: key,
                              score: 1,
                              completerId: 'twig',
                          }))
                        : []
                )
            );
        },
    };
}

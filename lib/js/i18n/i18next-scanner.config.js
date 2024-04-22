const typescriptTransform = require('i18next-scanner-typescript');

const dir = 'translations';

module.exports = {
    options: {
        debug: true,
        sort: true,
        removeUnusedKeys: true,
        func: {
            list: ['i18next.t', 'i18n.t'],
            extensions: ['.js', '.jsx']
        },
        trans: {
            component: 'Trans',
            i18nKey: 'i18nKey',
            defaultsKey: 'defaults',
            extensions: ['.js', '.jsx'],
            fallbackKey: function(ns, value) {
                return value;
            },

            // https://react.i18next.com/latest/trans-component#usage-with-simple-html-elements-like-less-than-br-greater-than-and-others-v10.4.0
            supportBasicHtmlNodes: true, // Enables keeping the name of simple nodes (e.g. <br/>) in translations instead of indexed keys.
            keepBasicHtmlNodesFor: ['br', 'strong', 'i', 'p'], // Which nodes are allowed to be kept in translations during defaultValue generation of <Trans>.

            // https://github.com/acornjs/acorn/tree/master/acorn#interface
            acorn: {
                ecmaVersion: 2020,
                sourceType: 'module', // defaults to 'module'
            }
        },
        lngs: ['en', 'fr', 'es', 'de', 'zh'],
        ns: ['lib'],
        defaultLng: 'en',
        defaultNs: 'lib',
        defaultValue: '__STRING_NOT_TRANSLATED__',
        resource: {
            loadPath: dir + '/{{lng}}.json',
            savePath: dir + '/{{lng}}.json',
            jsonIndent: 2,
            lineEnding: '\n'
        },
        nsSeparator: ':',
        keySeparator: '.',
        interpolation: {
            prefix: '{{',
            suffix: '}}'
        },
        metadata: {},
        allowDynamicKeys: false,
    },
    input: [
        'src/**/*.{ts,tsx,js,jsx}',
    ],
    output: './',
    transform: typescriptTransform(
        // options
        {
            // default value for extensions
            extensions: [".ts", ".tsx", ".jsx", ".js"],
            // optional ts configuration
            tsOptions: {
                target: "es2017",
                jsx: "preserve"
            },
        },

        // optional custom transform function
        function customTransform(outputText, file, enc, done) {
            "use strict";
            const parser = this.parser;
            let count = 0;

            parser.parseFuncFromString(outputText, { list: ['t'] }, (key, options) => {
                parser.set(key, Object.assign({}, options, {
                    nsSeparator: ':',
                    keySeparator: '.',
                }));
                ++count;
            });

            done();
        },
    ),
};

const {
    defineConfig,
    globalIgnores,
} = require("eslint/config");

const globals = require("globals");

const {
    fixupConfigRules,
} = require("@eslint/compat");

const tsParser = require("@typescript-eslint/parser");
const reactRefresh = require("eslint-plugin-react-refresh");
const unusedImports = require("eslint-plugin-unused-imports");
const js = require("@eslint/js");

const {
    FlatCompat,
} = require("@eslint/eslintrc");

const compat = new FlatCompat({
    baseDirectory: __dirname,
    recommendedConfig: js.configs.recommended,
    allConfig: js.configs.all
});

module.exports = defineConfig([{
    languageOptions: {
        globals: {
            ...globals.browser,
        },

        parser: tsParser,
    },

    extends: fixupConfigRules(compat.extends(
        "eslint:recommended",
        "plugin:@typescript-eslint/recommended",
        "plugin:react-hooks/recommended",
    )),

    plugins: {
        "react-refresh": reactRefresh,
        "unused-imports": unusedImports,
    },

    rules: {
        "@typescript-eslint/no-explicit-any": ["warn"],
        "no-unused-vars": "off",
        "no-console": "error",
        "@typescript-eslint/no-unused-vars": "off",
        "unused-imports/no-unused-imports-ts": "error",

        "unused-imports/no-unused-vars-ts": ["error", {
            vars: "all",
            varsIgnorePattern: "^_",
            args: "after-used",
            argsIgnorePattern: "^_",
        }],

        "@typescript-eslint/ban-types": ["error", {
            types: {
                "{}": false,
            },

            extendDefaults: true,
        }],

        "react/react-in-jsx-scope": "off",
        "no-empty-pattern": "off",
        "no-undef": "off",
        "react/prop-types": "off",
        "react/display-name": "off",
        "react/no-unescaped-entities": "off",
        "no-irregular-whitespace": "off",

        "react-refresh/only-export-components": ["warn", {
            allowConstantExport: true,
        }],
    },
}, globalIgnores([
    "**/dist",
    "**/.eslintrc.cjs",
    "src/TestMorph.tsx",
    "src/components/Media/Search/AQL/grammar.ts",
])]);

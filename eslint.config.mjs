// @ts-check
import eslint from '@eslint/js';
import {defineConfig} from 'eslint/config';
import tseslint from 'typescript-eslint';
import {reactRefresh} from 'eslint-plugin-react-refresh';
import reactHooks from 'eslint-plugin-react-hooks';
import reactLint from 'eslint-plugin-react';
import unusedImports from 'eslint-plugin-unused-imports';

export default defineConfig(
    /* Main config */
    reactRefresh.configs.recommended(),
    reactHooks.configs.flat.recommended,
    reactLint.configs.flat.recommended,
    eslint.configs.recommended,
    ...tseslint.configs.recommended,
    {
        rules: {
            '@typescript-eslint/ban-ts-comment': 'off',
            '@typescript-eslint/no-explicit-any': 'off',
            'no-console': 'error',
            '@typescript-eslint/no-unused-vars': [
                'error',
                {
                    argsIgnorePattern: '^_',
                    varsIgnorePattern: '^_',
                    caughtErrorsIgnorePattern: '^_',
                },
            ],
            'react/react-in-jsx-scope': 'off',
            'no-empty-pattern': 'off',
            'no-undef': 'off',
            'react/prop-types': 'off',
            'react/display-name': 'off',
            'react/no-unescaped-entities': 'off',
            'no-irregular-whitespace': 'off',
            '@typescript-eslint/no-empty-object-type': 'off',
            'react-refresh/only-export-components': [
                'warn',
                {
                    allowConstantExport: true,
                },
            ],
            'react-hooks/preserve-manual-memoization': 'off',
            'react-hooks/set-state-in-effect': 'off',
            'react-hooks/refs': 'off',
            'no-restricted-imports': [
                'error',
                {
                    patterns: [
                        {
                            regex: '(\.\./)*lib/js',
                            message:
                                'usage of lib/js modules not allowed, use @alchemy instead.',
                        },
                    ],
                },
            ],
        },
    },
    {
        plugins: {
            'unused-imports': unusedImports,
        },
        rules: {
            'unused-imports/no-unused-imports': 'error',
        },
    }
);

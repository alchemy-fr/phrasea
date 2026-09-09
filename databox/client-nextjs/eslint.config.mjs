import {defineConfig, globalIgnores} from 'eslint/config';
import nextVitals from 'eslint-config-next/core-web-vitals';
import nextTs from 'eslint-config-next/typescript';

export default defineConfig([
    ...nextVitals,
    ...nextTs,
    {
        settings: {
            react: {version: '19.2'},
        },
        rules: {
            'no-console': ['error', {allow: ['warn', 'error', 'debug']}],
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/no-unused-vars': [
                'error',
                {
                    argsIgnorePattern: '^_',
                    varsIgnorePattern: '^_',
                    caughtErrorsIgnorePattern: '^_',
                },
            ],
            'react-hooks/set-state-in-effect': 'off',
            'react-hooks/refs': 'off',
            'react-hooks/exhaustive-deps': 'warn',
        },
    },
    globalIgnores(['.next/**', 'node_modules/**', 'next-env.d.ts']),
]);

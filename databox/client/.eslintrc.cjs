module.exports = {
    root: true,
    env: {browser: true, es2020: true},
    extends: [
        'eslint:recommended',
        'plugin:@typescript-eslint/recommended',
        'plugin:react-hooks/recommended',
    ],
    ignorePatterns: [
        'dist',
        '.eslintrc.cjs',
        'src/TestMorph.tsx',
    ],
    parser: '@typescript-eslint/parser',
    plugins: ['react-refresh', 'unused-imports'],
    rules: {
        '@typescript-eslint/no-explicit-any': ['warn'],
        'no-unused-vars': 'off',
        '@typescript-eslint/no-unused-vars': 'off',
        'unused-imports/no-unused-imports-ts': 'error',
        'unused-imports/no-unused-vars-ts': [
            'error',
            {
                vars: 'all',
                varsIgnorePattern: '^_',
                args: 'after-used',
                argsIgnorePattern: '^_',
            },
        ],
        '@typescript-eslint/ban-types': [
            'error',
            {
                types: {
                    '{}': false,
                },
                extendDefaults: true,
            },
        ],
        'react/react-in-jsx-scope': 'off',
        'no-empty-pattern': 'off',
        'no-undef': 'off',
        'react/prop-types': 'off',
        'react/display-name': 'off',
        'react/no-unescaped-entities': 'off',
        'no-irregular-whitespace': 'off',
        'react-refresh/only-export-components': [
            'warn',
            {allowConstantExport: true},
        ],
    },
};

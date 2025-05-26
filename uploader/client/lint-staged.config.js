/**
 * @filename: lint-staged.config.js
 * @type {import('lint-staged').Configuration}
 */
export default {
    '*.{ts,tsx,js,jsx}': () => 'pnpm cs',
}

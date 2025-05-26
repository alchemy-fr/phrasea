/**
 * @filename: lint-staged.config.js
 * @type {import('lint-staged').Configuration}
 */
export default {
    'databox/client/**/*.(t|j)sx?': ['cd databox/client', 'pnpm cs'],
    'dashboard/client/**/*.(t|j)sx?': ['cd dashboard/client', 'pnpm cs'],
    'expose/client/**/*.(t|j)sx?': ['cd expose/client', 'pnpm cs'],

    'configurator/**/*.php': ['cd configurator', 'composer cs'],
    'databox/api/**/*.php': ['cd databox/api', 'composer cs'],
    'expose/api/**/*.php': ['cd databox/api', 'composer cs'],
    'uploader/api/**/*.php': ['cd uploader/api', 'composer cs'],
}

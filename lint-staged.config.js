export default {
    'databox/client/**/*.[jt]sx?': ['cd databox/client && pnpm cs'],
    'dashboard/client/**/*.[jt]sx?': ['cd dashboard/client && pnpm cs'],
    'expose/client/**/*.[jt]sx?': ['cd expose/client && pnpm cs'],

    'configurator/**/*.php': ['cd configurator && composer cs'],
    'databox/api/**/*.php': ['cd databox/api && composer cs'],
    'expose/api/**/*.php': ['cd databox/api && composer cs'],
    'uploader/api/**/*.php': ['cd uploader/api && composer cs'],
}

import { createDefaultPreset, JestConfigWithTsJest } from 'ts-jest'

const jestConfig: JestConfigWithTsJest = {
    testPathIgnorePatterns: [
        "/node_modules/",
        "/dist/",
        "/i18n-scan-tmp/"
    ],
    ...createDefaultPreset(),

}

export default jestConfig;

module.exports = {
    preset: 'ts-jest',
    testEnvironment: 'node',
    moduleNameMapper: {
        '^axios$': 'axios/dist/node/axios.cjs',
    },
};

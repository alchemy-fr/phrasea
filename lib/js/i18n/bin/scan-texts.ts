import StringScanner from "../src/StringScanner/StringScanner";

const debugEnabled = false;
const testFile = 'src/TestMorph.tsx';
// const testFile = 'src/api/clearAssociation.ts';

const scanner = new StringScanner({
    testFile,
    dryRun: debugEnabled,
    debug: debugEnabled,
});

scanner.run();

import StringScanner from "../src/StringScanner/StringScanner";

const debugEnabled = true;
// const testFile = 'src/TestMorph.tsx';
const testFile = 'src/components/Media/Asset/Attribute/Attributes.tsx';

const scanner = new StringScanner({
    testFile,
    dryRun: debugEnabled,
    debug: debugEnabled,
});

scanner.run();

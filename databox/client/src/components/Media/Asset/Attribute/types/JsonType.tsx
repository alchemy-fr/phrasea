import CodeType from './CodeType';
import 'ace-builds/src-noconflict/mode-json';

export default class JsonType extends CodeType {
    protected getAceMode(): string {
        return 'json';
    }

    protected prettifyCode(code: string): string {
        return JSON.stringify(JSON.parse(code), null, 2);
    }
}

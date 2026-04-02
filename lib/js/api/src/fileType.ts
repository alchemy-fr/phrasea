import {FileTypeParser} from 'file-type';
import {detectPdf} from '@file-type/pdf';
import {detectAv} from '@file-type/av';
import {detectXml} from '@file-type/xml';
import {detectCfbf} from '@file-type/cfbf';
import {Buffer as BufferPolyfill} from 'buffer';
globalThis.Buffer = BufferPolyfill;

export async function getFileType(file: File): Promise<string> {
    const parser = new FileTypeParser({
        customDetectors: [detectPdf, detectAv, detectXml, detectCfbf],
    });

    const result = await parser.fromBuffer(await file.arrayBuffer());
    if (result) {
        return result.mime;
    }

    return file.type;
}

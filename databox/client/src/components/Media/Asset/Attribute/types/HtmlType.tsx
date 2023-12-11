import CodeType from './CodeType';
import 'ace-builds/src-noconflict/mode-html';
import {AttributeFormatterProps} from './types';
import React from 'react';

export default class HtmlType extends CodeType {
    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <div dangerouslySetInnerHTML={{__html: value}} />;
    }

    protected getAceMode(): string {
        return 'html';
    }
}

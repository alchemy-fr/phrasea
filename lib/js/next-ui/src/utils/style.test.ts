import {describe, expect, it} from 'vitest';
import {parseInlineStyle} from './style';

describe('parseInlineStyle', () => {
    it('turns declarations into a React style object', () => {
        expect(parseInlineStyle('margin: 4px; max-height:20px;')).toEqual({
            margin: '4px',
            maxHeight: '20px',
        });
        expect(
            parseInlineStyle('background: url(https://a.test/x.png)')
        ).toEqual({background: 'url(https://a.test/x.png)'});
        expect(parseInlineStyle(undefined)).toBeUndefined();
    });
});

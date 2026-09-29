import {describe, expect, it} from 'vitest';
import {parseInline, parseMessage} from './messageMarkup';

describe('parseInline', () => {
    it('parses legacy mentions', () => {
        expect(parseInline('hi @[john](u-1), ok')).toEqual([
            {type: 'text', text: 'hi '},
            {type: 'mention', username: 'john', id: 'u-1'},
            {type: 'text', text: ', ok'},
        ]);
    });

    it('parses mentions of e-mail usernames', () => {
        expect(parseInline('@[jo.hn@x.org](42)')).toEqual([
            {type: 'mention', username: 'jo.hn@x.org', id: '42'},
        ]);
    });

    it('parses emphasis, nested', () => {
        expect(parseInline('**bold _it_** ~~del~~ *em*')).toEqual([
            {
                type: 'strong',
                children: [
                    {type: 'text', text: 'bold '},
                    {type: 'em', children: [{type: 'text', text: 'it'}]},
                ],
            },
            {type: 'text', text: ' '},
            {type: 'del', children: [{type: 'text', text: 'del'}]},
            {type: 'text', text: ' '},
            {type: 'em', children: [{type: 'text', text: 'em'}]},
        ]);
    });

    it('leaves snake_case, arithmetic and lonely markers alone', () => {
        for (const text of [
            'my_var_name',
            '2*3*4',
            'a * b * c',
            '** not bold **',
            'price: 5$',
        ]) {
            expect(parseInline(text)).toEqual([{type: 'text', text}]);
        }
    });

    it('does not format inside code', () => {
        expect(parseInline('`**x** @[a](1)`')).toEqual([
            {type: 'code', text: '**x** @[a](1)'},
        ]);
    });

    it('parses links and bare URLs without trailing punctuation', () => {
        expect(
            parseInline('[doc](https://a.io/x) see https://b.io/y?z=1.')
        ).toEqual([
            {type: 'link', href: 'https://a.io/x', label: 'doc'},
            {type: 'text', text: ' see '},
            {
                type: 'link',
                href: 'https://b.io/y?z=1',
                label: 'https://b.io/y?z=1',
            },
            {type: 'text', text: '.'},
        ]);
        expect(parseInline('(https://b.io/a_(b))')).toEqual([
            {type: 'text', text: '('},
            {
                type: 'link',
                href: 'https://b.io/a_(b)',
                label: 'https://b.io/a_(b)',
            },
            {type: 'text', text: ')'},
        ]);
    });

    it('never links other schemes', () => {
        expect(parseInline('[x](javascript:alert(1))')).toEqual([
            {type: 'text', text: '[x](javascript:alert(1))'},
        ]);
    });
});

describe('parseMessage', () => {
    it('keeps line breaks within paragraphs', () => {
        expect(parseMessage('a\nb\n\nc')).toEqual([
            {
                type: 'paragraph',
                children: [
                    {type: 'text', text: 'a'},
                    {type: 'br'},
                    {type: 'text', text: 'b'},
                ],
            },
            {type: 'paragraph', children: [{type: 'text', text: 'c'}]},
        ]);
    });

    it('parses lists, quotes and code blocks', () => {
        expect(
            parseMessage(
                'intro\n- a\n- **b**\n3. x\n4. y\n> q\n```\nx  *y*\n```'
            )
        ).toEqual([
            {type: 'paragraph', children: [{type: 'text', text: 'intro'}]},
            {
                type: 'list',
                ordered: false,
                start: 1,
                items: [
                    [{type: 'text', text: 'a'}],
                    [{type: 'strong', children: [{type: 'text', text: 'b'}]}],
                ],
            },
            {
                type: 'list',
                ordered: true,
                start: 3,
                items: [
                    [{type: 'text', text: 'x'}],
                    [{type: 'text', text: 'y'}],
                ],
            },
            {type: 'quote', children: [{type: 'text', text: 'q'}]},
            {type: 'codeblock', text: 'x  *y*'},
        ]);
    });

    it('keeps an unclosed fence as text', () => {
        expect(parseMessage('```\nx')).toEqual([
            {
                type: 'paragraph',
                children: [
                    {type: 'text', text: '```'},
                    {type: 'br'},
                    {type: 'text', text: 'x'},
                ],
            },
        ]);
    });

    it('handles empty content', () => {
        expect(parseMessage('')).toEqual([]);
        expect(parseMessage(null)).toEqual([]);
    });
});

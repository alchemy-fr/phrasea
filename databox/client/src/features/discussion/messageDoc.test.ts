import {describe, expect, it} from 'vitest';
import {getSchema, JSONContent} from '@tiptap/core';
import {docToMarkup, markupToDoc, quoteMarkup} from './messageDoc';
import {messageExtensions} from './editorExtensions';

const schema = getSchema(messageExtensions());

/** Goes through the editor schema, as the composer does */
function load(markup: string): JSONContent {
    const node = schema.nodeFromJSON(markupToDoc(markup));
    node.check();

    return node.toJSON();
}

describe('markup => document => markup', () => {
    it.each([
        'Hello',
        'Hi @[john](u-1), see @[jo.hn@x.org](42)!',
        '**bold _italic_** ~~strike~~ `code` _it_',
        'a **b** c',
        '**bold with @[john](1) inside**',
        '[doc](https://a.io/x) and https://b.io/y?z=1.',
        '**see [doc](https://a.io)**',
        'line 1\nline 2',
        'para 1\n\npara 2',
        '- a\n- **b**\n- @[john](1)',
        '3. x\n4. y',
        '> quoted\n> **line**\n>\n> after a blank line',
        '```\nconst x = **1**;\n  indented\n```',
        'intro\n- a\n- b\n> q\nend',
        'my_var_name and 2*3*4',
        '[x](javascript:alert(1))',
    ])('round-trips %j', markup => {
        expect(docToMarkup(load(markup))).toBe(markup);
    });

    it('normalizes the equivalent syntaxes', () => {
        expect(docToMarkup(load('*it*'))).toBe('_it_');
        expect(docToMarkup(load('* a\n+ b'))).toBe('- a\n- b');
        expect(docToMarkup(load('1) a'))).toBe('1. a');
    });

    it('loads an empty message as an empty paragraph', () => {
        expect(load('')).toEqual({type: 'doc', content: [{type: 'paragraph'}]});
        expect(docToMarkup(load(''))).toBe('');
    });

    it('builds nodes and marks', () => {
        expect(load('**a** @[jo](1)\nb').content).toEqual([
            {
                type: 'paragraph',
                content: [
                    {type: 'text', text: 'a', marks: [{type: 'bold'}]},
                    {type: 'text', text: ' '},
                    {type: 'mention', attrs: {id: '1', username: 'jo'}},
                    {type: 'hardBreak'},
                    {type: 'text', text: 'b'},
                ],
            },
        ]);
    });
});

describe('docToMarkup', () => {
    const p = (...content: JSONContent[]): JSONContent => ({
        type: 'paragraph',
        content,
    });
    const text = (t: string, ...marks: string[]): JSONContent => ({
        type: 'text',
        text: t,
        ...(marks.length ? {marks: marks.map(type => ({type}))} : {}),
    });
    const doc = (...content: JSONContent[]) => ({type: 'doc', content});

    it('keeps blanks out of the markers', () => {
        expect(
            docToMarkup(doc(p(text('a'), text(' b ', 'bold'), text('c'))))
        ).toBe('a **b** c');
        expect(docToMarkup(doc(p(text('a'), text('  ', 'italic'))))).toBe('a');
    });

    it('nests overlapping marks', () => {
        expect(
            docToMarkup(
                doc(
                    p(
                        text('a', 'bold'),
                        text('b', 'bold', 'italic'),
                        text('c', 'italic')
                    )
                )
            )
        ).toBe('**a_b_**_c_');
    });

    it('drops empty paragraphs and non-http links', () => {
        expect(
            docToMarkup(
                doc(
                    p(),
                    p(text('a')),
                    {type: 'paragraph'},
                    p({
                        type: 'text',
                        text: 'mail',
                        marks: [{type: 'link', attrs: {href: 'mailto:x@y'}}],
                    })
                )
            )
        ).toBe('a\n\nmail');
    });

    it('flattens nested lists and multi-paragraph quotes', () => {
        expect(
            docToMarkup(
                doc(
                    {
                        type: 'bulletList',
                        content: [
                            {
                                type: 'listItem',
                                content: [
                                    p(text('a')),
                                    {
                                        type: 'orderedList',
                                        attrs: {start: 1},
                                        content: [
                                            {
                                                type: 'listItem',
                                                content: [p(text('b'))],
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                    {
                        type: 'blockquote',
                        content: [p(text('q1')), p(), p(text('q2')), p()],
                    }
                )
            )
        ).toBe('- a\n1. b\n> q1\n>\n> q2');
    });
});

describe('quoteMarkup', () => {
    it('prefixes every line', () => {
        expect(quoteMarkup('a\n\n- b\n')).toBe('> a\n>\n> - b');
        expect(docToMarkup(load(`${quoteMarkup('**a**\nb')}\n\nreply`))).toBe(
            '> **a**\n> b\nreply'
        );
    });
});

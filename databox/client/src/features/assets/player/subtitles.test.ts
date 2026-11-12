import {describe, expect, it} from 'vitest';
import {AttributeType, type Attribute} from '@/types/api';
import {getSubtitleTracks} from './subtitles';

const vtt = 'WEBVTT\n\n00:00.000 --> 00:05.000\nHello';

function attribute(
    id: string,
    type: AttributeType,
    value: unknown,
    locale?: string,
    name = 'Transcript'
): Attribute {
    return {
        id,
        definition: {id: `d-${name}`, name, displayName: name, type},
        value,
        locale,
    } as unknown as Attribute;
}

describe('getSubtitleTracks', () => {
    it('makes a track of every WebVTT value, in the language of its locale', () => {
        const tracks = getSubtitleTracks(
            [
                attribute('en', AttributeType.WebVtt, vtt, 'en'),
                attribute('fr', AttributeType.WebVtt, vtt, 'fr_FR'),
                attribute('code', AttributeType.Code, vtt, 'en'),
                attribute('text', AttributeType.Text, 'WEBVTT'),
            ],
            'en',
            ['en']
        );

        expect(tracks).toEqual([
            {
                id: 'en',
                label: 'Transcript (English)',
                locale: 'en',
                src: vtt,
                default: true,
            },
            {
                id: 'fr',
                label: 'Transcript (French (France))',
                locale: 'fr-FR',
                src: vtt,
            },
        ]);
    });

    it('names a single track after its attribute', () => {
        expect(
            getSubtitleTracks(
                [attribute('en', AttributeType.WebVtt, vtt, 'en')],
                'en'
            )
        ).toEqual([
            {
                id: 'en',
                label: 'Transcript',
                locale: 'en',
                src: vtt,
                default: true,
            },
        ]);
    });

    it('names a value without a locale after its attribute, without a language', () => {
        const [untranslated, english] = getSubtitleTracks(
            [
                attribute('u', AttributeType.WebVtt, vtt, '_', 'Subtitles'),
                attribute('en', AttributeType.WebVtt, vtt, 'en'),
            ],
            'fr',
            ['fr']
        );

        expect(untranslated).toEqual({
            id: 'u',
            label: 'Subtitles',
            locale: undefined,
            src: vtt,
            default: true,
        });
        expect(english.label).toBe('Transcript (Anglais)');
    });

    it('leaves the empty values out', () => {
        expect(
            getSubtitleTracks(
                [
                    attribute('blank', AttributeType.WebVtt, '  ', 'en'),
                    attribute('none', AttributeType.WebVtt, null, 'fr'),
                ],
                'en'
            )
        ).toEqual([]);
        expect(getSubtitleTracks(undefined)).toEqual([]);
    });

    describe('the track shown by default', () => {
        const defaults = (tracks: {id: string; default?: boolean}[]) =>
            tracks.filter(t => t.default).map(t => t.id);

        it('is the single one', () => {
            expect(
                defaults(
                    getSubtitleTracks(
                        [attribute('en', AttributeType.WebVtt, vtt, 'en')],
                        'en',
                        ['fr']
                    )
                )
            ).toEqual(['en']);
        });

        it('is the one of the preferred language', () => {
            expect(
                defaults(
                    getSubtitleTracks(
                        [
                            attribute('en', AttributeType.WebVtt, vtt, 'en'),
                            attribute('fr', AttributeType.WebVtt, vtt, 'fr_FR'),
                            attribute('u', AttributeType.WebVtt, vtt, '_'),
                        ],
                        'fr',
                        ['fr-FR', 'en']
                    )
                )
            ).toEqual(['fr']);
        });

        it('is the first one without a value in the preferred language', () => {
            expect(
                defaults(
                    getSubtitleTracks(
                        [
                            attribute('de', AttributeType.WebVtt, vtt, 'de'),
                            attribute('es', AttributeType.WebVtt, vtt, 'es'),
                        ],
                        'en',
                        ['en']
                    )
                )
            ).toEqual(['de']);
        });

        it('is the value without a locale when it is the only one', () => {
            expect(
                defaults(
                    getSubtitleTracks(
                        [
                            attribute('u', AttributeType.WebVtt, vtt),
                            attribute('blank', AttributeType.WebVtt, '', 'en'),
                        ],
                        'en',
                        ['en']
                    )
                )
            ).toEqual(['u']);
        });

        it('is one value of the first WebVTT attribute', () => {
            expect(
                defaults(
                    getSubtitleTracks(
                        [
                            attribute(
                                'a-en',
                                AttributeType.WebVtt,
                                vtt,
                                'en',
                                'A'
                            ),
                            attribute(
                                'b-fr',
                                AttributeType.WebVtt,
                                vtt,
                                'fr',
                                'B'
                            ),
                            attribute(
                                'a-fr',
                                AttributeType.WebVtt,
                                vtt,
                                'fr',
                                'A'
                            ),
                        ],
                        'fr',
                        ['fr']
                    )
                )
            ).toEqual(['a-fr']);
        });
    });
});

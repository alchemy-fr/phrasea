import {describe, expect, it} from 'vitest';
import type {TFunction} from 'i18next';
import {booleanChoices} from './booleanChoices';

const t = ((_key: string, fallback: string) => fallback) as TFunction;

describe('booleanChoices', () => {
    it('offers Yes then No by default', () => {
        expect(booleanChoices({}, t)).toEqual([
            {value: true, label: 'Yes'},
            {value: false, label: 'No'},
        ]);
    });

    it('takes the labels and the order of the profile item', () => {
        expect(
            booleanChoices(
                {trueLabel: 'Stories', falseLabel: 'Assets', reversed: true},
                t
            )
        ).toEqual([
            {value: false, label: 'Assets'},
            {value: true, label: 'Stories'},
        ]);
    });

    it('falls back to the default label of an empty one', () => {
        expect(booleanChoices({trueLabel: '', falseLabel: null}, t)).toEqual([
            {value: true, label: 'Yes'},
            {value: false, label: 'No'},
        ]);
    });
});

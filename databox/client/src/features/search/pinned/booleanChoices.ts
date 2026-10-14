import type {TFunction} from 'i18next';
import type {ProfileItem} from '@/types/api';

export type BooleanChoice = {value: boolean; label: string};

/** The values of a pinned boolean filter, labelled and ordered by its item */
export function booleanChoices(
    item: Pick<ProfileItem, 'trueLabel' | 'falseLabel' | 'reversed'>,
    t: TFunction
): BooleanChoice[] {
    const choices = [
        {value: true, label: item.trueLabel || t('common.yes', 'Yes')},
        {value: false, label: item.falseLabel || t('common.no', 'No')},
    ];

    return item.reversed ? choices.reverse() : choices;
}

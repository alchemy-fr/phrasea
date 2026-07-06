import {CompositeOption, CompositeValue, SelectOption} from '../types';

export function valueToOption<
    IsMulti extends boolean,
    Opt extends SelectOption,
>(
    isMulti: IsMulti,
    value: CompositeValue<IsMulti>,
    lastOptions: Record<string, Opt> = {}
): CompositeOption<IsMulti, Opt> {
    if (isMulti) {
        if (!value) {
            return [] as any;
        }
        return (value as string[]).map(v =>
            valueToOption(false, v, lastOptions)
        ) as CompositeOption<IsMulti, Opt>;
    } else if (value) {
        return (lastOptions[value as string] ?? null) as CompositeOption<
            IsMulti,
            Opt
        >;
    }

    return null as CompositeOption<IsMulti, Opt>;
}

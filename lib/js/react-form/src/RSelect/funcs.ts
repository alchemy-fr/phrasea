import {CompositeOption, CompositeValue, SelectOption} from '../types';

export function valueToOption<
    IsMulti extends boolean,
    Opt extends SelectOption,
>(
    isMulti: IsMulti,
    value: CompositeValue<IsMulti> | CompositeOption<IsMulti, Opt>,
    lastOptions: Record<string, Opt> = {}
): CompositeOption<IsMulti, Opt> {
    if (isMulti) {
        if (!value) {
            return [] as unknown as CompositeOption<IsMulti, Opt>;
        }
        return (value as string[]).map(v =>
            valueToOption(false, v, lastOptions)
        ) as CompositeOption<IsMulti, Opt>;
    } else if (value) {
        if (typeof value === 'string') {
            return (lastOptions[value as string] ?? null) as CompositeOption<
                IsMulti,
                Opt
            >;
        } else if (Object.prototype.hasOwnProperty.call(value, 'id')) {
            return (lastOptions[(value as unknown as {id: string}).id] ??
                null) as CompositeOption<IsMulti, Opt>;
        }

        return value as CompositeOption<IsMulti, Opt>;
    }

    return null as CompositeOption<IsMulti, Opt>;
}

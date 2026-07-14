import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {listTargets} from '../api/targetApi.ts';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues> = AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function TargetSelectWidget<TFieldValues extends FieldValues>(
    props: Props<TFieldValues>
) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            listTargets({
                ...props,
            }),
        map: t => {
            return {
                value: t['@id'],
                label: t.name,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'target'}
            {...props}
            loadOptions={loadOptions}
        />
    );
}

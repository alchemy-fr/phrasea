import {IntegrationType} from '../../types';
import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {getIntegrationTypes} from '../../api/integrations.ts';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues> = {} & AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function IntegrationTypeSelect<
    TFieldValues extends FieldValues,
>({...rest}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props => getIntegrationTypes(props),
        map: (t: IntegrationType) => ({
            value: t.id,
            label: t.displayName,
        }),
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'integration-types'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}

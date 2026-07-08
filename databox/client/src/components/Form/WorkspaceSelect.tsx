import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {getWorkspaces} from '../../api/workspace.ts';
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues> = {} & AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function WorkspaceSelect<TFieldValues extends FieldValues>({
    ...rest
}: Props<TFieldValues>) {
    const store = useEntitiesStore(s => s.store);

    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getWorkspaces({
                ...props,
            }),
        map: t => {
            store(t['@id'], t);

            return {
                value: t.id,
                label: t.displayName ?? t.name,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'workspaces'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}

import {useCallback} from 'react';
import {Workspace} from '../../types';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {getWorkspaces} from '../../api/workspace.ts';
import {useEntitiesStore} from '../../store/entitiesStore.ts';

type Props<TFieldValues extends FieldValues> = {} & AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function WorkspaceSelect<TFieldValues extends FieldValues>({
    ...rest
}: Props<TFieldValues>) {
    const store = useEntitiesStore(s => s.store);

    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = (await getWorkspaces()).result;

            return data
                .map((t: Workspace) => {
                    store(t['@id'], t);

                    return {
                        value: t.id,
                        label: t.nameTranslated ?? t.name,
                    };
                })
                .filter(i =>
                    i.label
                        .toLowerCase()
                        .includes((inputValue || '').toLowerCase())
                );
        },
        []
    );

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'workspaces'}
            {...rest}
            loadOptions={load}
        />
    );
}

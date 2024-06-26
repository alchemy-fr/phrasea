import {useCallback} from 'react';
import {FieldValues} from 'react-hook-form';
import {getRenditionClasses, renditionClassNS} from '../../api/rendition';
import {RenditionClass} from '../../types';
import {
    AsyncRSelectWidget,
    AsyncRSelectProps,
    SelectOption,
} from '@alchemy/react-form';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function RenditionClassSelect<TFieldValues extends FieldValues>({
    workspaceId,
    ...rest
}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getRenditionClasses(workspaceId);

            return data.result
                .map((t: RenditionClass) => ({
                    value: `${renditionClassNS}/${t.id}`,
                    label: t.name,
                }))
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
            cacheId={'rend-classes'}
            {...rest}
            loadOptions={load}
        />
    );
}

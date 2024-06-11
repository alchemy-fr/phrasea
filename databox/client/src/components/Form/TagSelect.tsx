import {Tag} from '../../types';
import {getTags, tagNS} from '../../api/tag';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectWidget,
    AsyncRSelectProps,
    SelectOption,
} from '@alchemy/react-form';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function TagSelect<TFieldValues extends FieldValues>({
    workspaceId,
    ...rest
}: Props<TFieldValues>) {
    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = (
            await getTags({
                workspace: workspaceId,
            })
        ).result;

        return data
            .map((t: Tag) => ({
                value: `${tagNS}/${t.id}`,
                label: t.nameTranslated,
            }))
            .filter(i =>
                i.label.toLowerCase().includes((inputValue || '').toLowerCase())
            );
    };

    return (
        <AsyncRSelectWidget<TFieldValues, false>
            cacheId={'tags'}
            {...rest}
            loadOptions={load}
            isMulti={true as any}
            key={workspaceId}
        />
    );
}

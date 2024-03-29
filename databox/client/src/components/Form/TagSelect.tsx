import {Tag} from '../../types';
import {getTags, tagNS} from '../../api/tag';
import {FieldValues} from 'react-hook-form';
import RSelectWidget, {RSelectProps, SelectOption} from './RSelect';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & RSelectProps<TFieldValues, false>;

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
        <RSelectWidget<TFieldValues, false>
            cacheId={'tags'}
            {...rest}
            loadOptions={load}
            isMulti={true as any}
            key={workspaceId}
        />
    );
}

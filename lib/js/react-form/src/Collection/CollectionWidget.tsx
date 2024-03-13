import {Control, FieldErrors, FieldValues, useFieldArray, UseFieldArrayUpdate, UseFormRegister} from 'react-hook-form';
import {Button, InputLabel} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';

type BaseProps<TFieldValues extends FieldValues> = {
    control: Control<TFieldValues>;
    register: UseFormRegister<TFieldValues>;
    max?: number;
};

export type BaseCollectionProps<TFieldValues extends FieldValues> = {
    name: string;
    errors: FieldErrors<TFieldValues>;
} & BaseProps<TFieldValues>;

export type RenderForm<TFieldValues extends FieldValues> = (props: {
    register: UseFormRegister<TFieldValues>;
    path: string;
    errors: FieldErrors;
    index: number;
    data: any | undefined;
    update: UseFieldArrayUpdate<TFieldValues, any>;
}) => ReactNode;

type Props<TFieldValues extends FieldValues> = {
    path: string;
    emptyItem: any;
    label: ReactNode;
    removeLabel?: ReactNode;
    addLabel?: ReactNode;
    renderForm: RenderForm<TFieldValues>;
    errors: FieldErrors;
} & BaseProps<TFieldValues>;

export type {Props as CollectionWidgetProps};

export default function CollectionWidget<TFieldValues extends FieldValues>({
    path,
    emptyItem,
    renderForm,
    control,
    register,
    label,
    errors,
    max,
    removeLabel,
    addLabel,
}: Props<TFieldValues>) {
    const {fields, remove, append, update} = useFieldArray<TFieldValues>({
        control,
        name: path as unknown as any,
    });

    const {t} = useTranslation();

    const appendItem = () => {
        append(typeof emptyItem === 'string' ? emptyItem : {
            ...emptyItem,
        } as any);
    };

    if (0 === max) {
        return <></>;
    }

    return (
        <div>
            <InputLabel>{label}</InputLabel>
            {fields.map((field, index) => (
                <div key={field.id}>
                    {renderForm({
                        register,
                        errors,
                        index,
                        path,
                        update,
                        data: field,
                    })}
                    <div className={'f-remove-item'}>
                        <Button
                            onClick={() => remove(index)}
                            startIcon={<DeleteIcon/>}
                            color={'error'}
                        >
                            {removeLabel ||
                                t('form.collection.remove', 'Remove')}
                        </Button>
                    </div>
                </div>
            ))}

            {undefined === max || fields.length < max ? (
                <Button onClick={appendItem} startIcon={<AddIcon/>}>
                    {addLabel || t('form.collection.add', 'Add')}
                </Button>
            ) : (
                ''
            )}
        </div>
    );
}

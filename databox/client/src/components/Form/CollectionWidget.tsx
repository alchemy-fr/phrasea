import {Control, UseFormRegister} from "react-hook-form/dist/types/form";
import {Box, Button, InputLabel} from "@mui/material";
import {useFieldArray} from "react-hook-form";
import AddIcon from "@mui/icons-material/Add";
import DeleteIcon from "@mui/icons-material/Delete";
import {ReactNode} from "react";
import {useTranslation} from "react-i18next";
import SortIcon from "@mui/icons-material/Sort";

type BaseProps<TFieldValues> = {
    control: Control<TFieldValues>;
    register: UseFormRegister<TFieldValues>;
}

type Props<TFieldValues> = {
    path: string;
    emptyItem: object;
    label: ReactNode;
    removeLabel?: ReactNode;
    addLabel?: ReactNode;
    renderForm: RenderForm<TFieldValues>;
} & BaseProps<TFieldValues>;

export type {Props as CollectionWidgetProps};

type RenderForm<TFieldValues> = (props: {
    register: UseFormRegister<TFieldValues>;
    path: string;
    index: number;
}) => ReactNode;

type ItemProps<TFieldValues> = {
    renderForm: RenderForm<TFieldValues>;
    remove: (index: number) => void;
    removeLabel: ReactNode;
    register: UseFormRegister<TFieldValues>;
    index: number,
    path: string,
    sortable?: boolean;
    dragListeners?: object;
};

export type {ItemProps as CollectionItemProps};

export function CollectionItem<TFieldValues>({
                                                 renderForm,
                                                 remove,
                                                 removeLabel,
                                                 register,
                                                 index,
                                                 path,
                                                 sortable,
                                                 dragListeners,
                                             }: ItemProps<TFieldValues>) {
    return <Box
        sx={{
            position: 'relative',
            paddingLeft: sortable ? 5 : undefined,
            mt: 2,
        }}
    >
        {sortable && <Box
            sx={{
                position: 'absolute',
                top: 10,
                left: 0,
                cursor: 'move',
            }}
            {...(dragListeners || {})}
        >
            <SortIcon/>
        </Box>}
        {renderForm({
            register,
            index,
            path,
        })}
        {remove && <div>
            <Button
                onClick={() => remove(index)}
                startIcon={<DeleteIcon/>}
                color={'error'}
                sx={{
                    position: 'absolute',
                    bottom: 10,
                    right: 10,
                }}
            >
                {removeLabel}
            </Button>
        </div>}
    </Box>
}

export default function CollectionWidget<TFieldValues>({
                                                           path,
                                                           emptyItem,
                                                           renderForm,
                                                           control,
                                                           register,
                                                           label,
                                                           removeLabel,
                                                           addLabel,
                                                       }: Props<TFieldValues>) {
    const {fields, remove, append} = useFieldArray<TFieldValues>({
        control,
        name: path as unknown as any,
    });

    const {t} = useTranslation();

    const appendItem = () => {
        append(emptyItem as any);
    };
    const rLabel = removeLabel || t('form.collection.remove', 'Remove');

    return <div>
        <InputLabel>{label}</InputLabel>
        {fields.map((field, index) => {
            return <CollectionItem
                key={field.id}
                renderForm={renderForm}
                remove={remove}
                removeLabel={rLabel}
                register={register}
                path={path}
                index={index}
            />
        })}
        <Button
            onClick={appendItem}
            startIcon={<AddIcon/>}>
            {addLabel || t('form.collection.add', 'Add')}
        </Button>
    </div>
}

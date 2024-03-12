import {FieldErrors, FieldValues, UseFieldArrayUpdate, UseFormRegister} from "react-hook-form";
import {ReactNode} from "react";
import Box from "@mui/material/Box";
import SortIcon from "@mui/icons-material/Sort";
import {Button} from "@mui/material";
import DeleteIcon from "@mui/icons-material/Delete";
import {RenderForm} from "./CollectionWidget";

type ItemProps<TFieldValues extends FieldValues> = {
    renderForm: RenderForm<TFieldValues>;
    remove: (index: number) => void;
    removeLabel: ReactNode;
    update: UseFieldArrayUpdate<TFieldValues, any>;
    data: any | undefined;
    register: UseFormRegister<TFieldValues>;
    index: number;
    path: string;
    errors: FieldErrors;
    sortable?: boolean;
    dragListeners?: object;
};

export type {ItemProps as CollectionItemProps};

export function CollectionItem<TFieldValues extends FieldValues>({
    renderForm,
    remove,
    removeLabel,
    register,
    errors,
    index,
    update,
    data,
    path,
    sortable,
    dragListeners,
}: ItemProps<TFieldValues>) {
    return (
        <Box
            sx={{
                position: 'relative',
                paddingLeft: sortable ? 5 : undefined,
                mt: 2,
            }}
        >
            {sortable && (
                <Box
                    sx={{
                        position: 'absolute',
                        top: 10,
                        left: 0,
                        cursor: 'move',
                    }}
                    {...(dragListeners || {})}
                >
                    <SortIcon />
                </Box>
            )}
            {renderForm({
                register,
                index,
                path,
                errors,
                update,
                data,
            })}
            {remove && (
                <div>
                    <Button
                        onClick={() => remove(index)}
                        startIcon={<DeleteIcon />}
                        color={'error'}
                        sx={{
                            position: 'absolute',
                            bottom: 10,
                            right: 10,
                        }}
                    >
                        {removeLabel}
                    </Button>
                </div>
            )}
        </Box>
    );
}

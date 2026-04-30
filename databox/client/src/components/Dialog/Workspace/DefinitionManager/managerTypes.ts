import {ApiHydraObjectResponse, UseFormSubmitReturn} from '@alchemy/api';
import {Entity, StateSetter, Workspace} from '../../../../types.ts';
import React, {ReactNode} from 'react';
import {ButtonProps} from '@mui/material/Button';
import {ListItemButtonProps} from '@mui/material';
import {SortableItemProps} from '../../../Ui/Sortable/SortableList.tsx';

export enum ItemAction {
    None = 0,
    Create = 1,
    Update = 2,
    Manage = 3,
}

export type DefinitionBase = ApiHydraObjectResponse & Entity;
export type DefinitionItemProps<D extends DefinitionBase> = {
    data: D;
};
export type DefinitionManagerExtraProps = Record<string, any>;
export type DefinitionListItemProps<D extends DefinitionBase> = {
    onEdit: () => void;
    onDelete?: () => void;
} & DefinitionItemProps<D>;
export type DefinitionItemFormProps<
    D extends DefinitionBase,
    EP extends DefinitionManagerExtraProps = {},
> = {
    onSave: (data: D) => Promise<D>;
    onItemUpdate: (data: D) => void;
    usedFormSubmit: UseFormSubmitReturn<D>;
    workspace: Workspace;
    extraProps: EP;
} & DefinitionItemProps<D>;

type Reload = () => Promise<any>;

export type DefinitionItemManageProps<D extends DefinitionBase> = {
    workspace: Workspace;
    setSubManagementState: SetSubManagementState;
    reload: Reload;
} & DefinitionItemProps<D>;
export type ListState<D extends DefinitionBase> = {
    list: D[] | undefined;
    loading: boolean;
    loadingMore: boolean;
    next: string | undefined;
    query: string;
    filters: Filters;
};
export type ItemState<D extends DefinitionBase> = {
    item: D | undefined;
    loading: boolean;
    action: ItemAction;
};

export type OnSort = (ids: string[]) => void;

export type NormalizeData<D extends DefinitionBase> = (data: D) => D;

export type SubManagementState = {
    formId?: string | undefined;
    action: ItemAction;
};
export type SetSubManagementState = StateSetter<SubManagementState | undefined>;

export type BodyProps<D extends DefinitionBase> = {
    items: D[] | undefined;
    reload: () => Promise<void>;
};

export type BodyWithListLoadedProps<D extends DefinitionBase> = {
    items: D[];
} & BodyProps<D>;

export type Filters = Record<string, any>;

export type SetFilterFunc<F extends Filters> = (
    name: keyof F,
    value: any
) => void;

export type FilterProps<F extends Filters> = {
    filters: F;
    setFilter: SetFilterFunc<F>;
};

export type BatchAction<D extends DefinitionBase> = {
    id: string;
    label: ReactNode;
    color?: ButtonProps['color'];
    icon?: ButtonProps['startIcon'];
    confirm?: string;
    process: (
        items: D[],
        props: {
            reload: Reload;
        }
    ) => Promise<void>;
};
export type ListItemContainerProps<D extends DefinitionBase> = {
    item: D;
    listComponent: React.ComponentType<any>;
    selectedItem: D | undefined;
    selection?: string[];
    setSelection: StateSetter<string[]>;
    itemDeletable?: boolean;
    onDelete?: (item: D) => void;
    handleItemClick: (
        item: D,
        forceEdit?: boolean
    ) => ListItemButtonProps['onClick'];
};

export type SortableListItemProps<D extends DefinitionBase> = Omit<
    ListItemContainerProps<D>,
    'item'
>;

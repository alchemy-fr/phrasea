import React from 'react';
import FilterAltIcon from '@mui/icons-material/FilterAlt';
import IconButton from '@mui/material/IconButton';
import type {DropdownActionsProps} from '@alchemy/phrasea-ui';
import {DropdownActions} from '@alchemy/phrasea-ui';
import {Badge} from '@mui/material';

type Props = {
    activeFilterCount?: number;
} & Pick<DropdownActionsProps, 'children' | 'onClose'>;

export default function FilterDropdown({activeFilterCount, ...props}: Props) {
    return (
        <DropdownActions
            anchorOrigin={{
                vertical: 'bottom',
                horizontal: 'left',
            }}
            mainButton={({...props}) => (
                <Badge
                    anchorOrigin={{
                        vertical: 'top',
                        horizontal: 'left',
                    }}
                    badgeContent={activeFilterCount}
                    color="primary"
                    invisible={!activeFilterCount}
                >
                    <IconButton {...props}>
                        <FilterAltIcon />
                    </IconButton>
                </Badge>
            )}
            {...props}
        />
    );
}

import {PropsWithChildren} from 'react';
import {Chip, ChipProps} from '@mui/material';
import {Collection} from '../../types.ts';

type Props = {
    collection?: Collection;
} & PropsWithChildren<ChipProps>;

export const CollectionChip = ({children, collection, ...props}: Props) => (
    <Chip
        {...props}
        sx={theme => ({
            bgcolor: theme.palette.grey[300],
            color: theme.palette.grey[900],
        })}
        {...(collection?.deleted
            ? {
                  style: {textDecoration: 'line-through'},
              }
            : {})}
        label={
            children ||
            props.label ||
            collection?.titleTranslated ||
            collection?.title
        }
    />
);

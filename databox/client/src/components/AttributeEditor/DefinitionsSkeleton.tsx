import {ListItem, Skeleton} from '@mui/material';

type Props = {};

export default function DefinitionsSkeleton({}: Props) {
    return (
        <ListItem disablePadding>
            <Skeleton />
        </ListItem>
    );
}

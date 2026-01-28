import {ListItem, Skeleton} from '@mui/material';

type Props = {};

export default function BasketSkeleton({}: Props) {
    return (
        <>
            <ListItem>
                <Skeleton variant={'text'} width={'100%'} />
            </ListItem>
            <ListItem>
                <Skeleton variant={'text'} width={'100%'} />
            </ListItem>
        </>
    );
}

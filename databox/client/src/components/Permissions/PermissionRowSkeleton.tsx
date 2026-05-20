import {Button, Skeleton} from '@mui/material';

type Props = {columnCount: number};

export default function PermissionRowSkeleton({columnCount}: Props) {
    return (
        <tr>
            <td className={'ug'}>
                <Skeleton />
            </td>
            {Array(columnCount).map((_, i) => {
                return (
                    <td key={i} className={'p'}>
                        <Skeleton
                            variant="rectangular"
                            width={21}
                            height={21}
                            sx={{
                                display: 'inline-block',
                            }}
                        />
                    </td>
                );
            })}
            <td className={'a'}>
                <Button color={'error'}>
                    <Skeleton width={55} />
                </Button>
            </td>
        </tr>
    );
}

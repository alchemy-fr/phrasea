import {Button, Skeleton} from "@mui/material";
import React from "react";

export default function PermissionRowSkeleton({permissions}: {
    permissions: string[]
}) {
    return <tr>
        <td className={'ug'}>
            <Skeleton/>
        </td>
        {permissions.map((k) => {
            return <td
                key={k}
                className={'p'}
            >
                <Skeleton
                    variant="rectangular"
                    width={21}
                    height={21}
                    sx={{
                        display: 'inline-block',
                    }}
                />
            </td>
        })}
        <td className={'a'}>
            <Button
                color={'error'}
            >
                <Skeleton width={55}/>
            </Button>
        </td>
    </tr>
}

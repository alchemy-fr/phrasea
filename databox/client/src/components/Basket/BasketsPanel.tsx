import React from 'react';
import {List, ListItem, Skeleton} from "@mui/material";
import {getBaskets} from "../../api/basket.ts";
import {ApiCollectionResponse} from "../../api/hydra.ts";
import {Basket} from "../../types.ts";

type Props = {};

export default function BasketsPanel({}: Props) {
    const [baskets, setBaskets] = React.useState<ApiCollectionResponse<Basket>>();

    React.useEffect(() => {
        getBaskets().then(setBaskets);
    }, []);

    return (
        <List
            disablePadding
            component="nav"
            aria-labelledby="nested-list-subheader"
            sx={theme => ({
                root: {
                    width: '100%',
                    maxWidth: 360,
                    backgroundColor: theme.palette.background.paper,
                },
                nested: {
                    paddingLeft: theme.spacing(4),
                },
            })}
        >
            {baskets ? baskets.result.map(b => <ListItem
                key={b.id}
            >
                {b.title}
            </ListItem>) : <>
                <ListItem>
                    <Skeleton variant={'text'}  width={'100%'}/>
                </ListItem>
                <ListItem>
                    <Skeleton variant={'text'}  width={'100%'}/>
                </ListItem>
            </>}
        </List>
    );
}

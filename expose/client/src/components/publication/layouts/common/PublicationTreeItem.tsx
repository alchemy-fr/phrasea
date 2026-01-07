import {Publication} from '../../../../types.ts';
import {ListItemButton, ListItemText} from '@mui/material';
import {getTranslatedTitle} from '../../../../i18n.ts';
import {RenderItemProps} from '@alchemy/phrasea-framework';

type Props = {
    navigateToPublication: (publication: Publication) => void;
    item?: Partial<RenderItemProps<Publication>['item']>;
} & Omit<RenderItemProps<Publication>, 'item'>;

export default function PublicationTreeItem({
    navigateToPublication,
    data,
    level,
    item,
}: Props) {
    return (
        <ListItemButton
            selected={item?.selected}
            onClick={() => navigateToPublication(data)}
        >
            <ListItemText
                primaryTypographyProps={{
                    sx: {
                        pl: level,
                    },
                }}
                primary={getTranslatedTitle(data)}
            />
        </ListItemButton>
    );
}

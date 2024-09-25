import {
    ListItem,
    ListItemButton,
    ListItemProps,
    ListItemText,
} from '@mui/material';
import {Basket} from '../../types';
import {useTranslation} from 'react-i18next';
import {replaceHighlight} from '../Media/Asset/Attribute/AttributeHighlights.tsx';
import {Classes} from '../../classes.ts';

type Props = {
    data: Basket;
    selected?: boolean;
    onClick?: () => void;
} & Pick<ListItemProps, 'onContextMenu'>;

export default function BasketMenuItem({
    data,
    onClick,
    selected,
    onContextMenu,
}: Props) {
    const {t} = useTranslation();

    return (
        <>
            <ListItem onContextMenu={onContextMenu} disablePadding>
                <ListItemButton
                    selected={selected}
                    role={undefined}
                    onClick={onClick}
                >
                    <ListItemText
                        className={Classes.ellipsisText}
                        primary={
                            data.titleHighlight
                                ? replaceHighlight(data.titleHighlight)
                                : data.title ||
                                  t('basket.default.title', 'My Basket')
                        }
                        secondary={
                            data.descriptionHighlight
                                ? replaceHighlight(data.descriptionHighlight)
                                : data.description
                        }
                        secondaryTypographyProps={{
                            style: {whiteSpace: 'normal'},
                        }}
                    />
                </ListItemButton>
            </ListItem>
        </>
    );
}

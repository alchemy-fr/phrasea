import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    MenuList,
    Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import React, {memo} from 'react';
import {Asset, Collection} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import {BaseAttributeRowUIProps} from './Attribute/AttributeRowUI.tsx';
import FolderIcon from '@mui/icons-material/Folder';
import AssetThumb, {thumbSx} from './AssetThumb.tsx';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';
import {modalRoutes, Routing} from '../../../routes.ts';

type Props = {
    asset: Asset;
} & BaseAttributeRowUIProps;

function AssetAppearsIn({asset}: Props) {
    const [expanded, setExpanded] = React.useState(false);
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    return (
        <Accordion expanded={expanded} onChange={() => setExpanded(p => !p)}>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Typography component="div">
                    {t('asset.view.appears_in', `Appears in`)}
                </Typography>
            </AccordionSummary>
            <AccordionDetails
                sx={{
                    p: 0,
                }}
            >
                <MenuList
                    sx={theme => thumbSx(50, theme)}
                    disablePadding={true}
                >
                    {asset.collections?.map((c: Collection) => {
                        const storyAsset = c.storyAsset;
                        if (storyAsset) {
                            return (
                                <ListItemButton
                                    key={c.id}
                                    onClick={() => {
                                        navigateToModal(
                                            modalRoutes.assets.routes.view,
                                            {
                                                id: storyAsset!.id,
                                                renditionId:
                                                    storyAsset!.original?.id ||
                                                    Routing.UnknownRendition,
                                            }
                                        );
                                    }}
                                >
                                    <ListItemIcon>
                                        <AssetThumb
                                            asset={storyAsset}
                                            noStoryCarousel={true}
                                        />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={
                                            storyAsset.resolvedTitle ??
                                            storyAsset.title
                                        }
                                    />
                                </ListItemButton>
                            );
                        }

                        return (
                            <ListItem key={c.id} onClick={() => {}}>
                                <ListItemIcon>
                                    <FolderIcon />
                                </ListItemIcon>
                                <ListItemText
                                    primary={
                                        c.absoluteTitleTranslated ??
                                        c.absoluteTitle
                                    }
                                />
                            </ListItem>
                        );
                    })}
                </MenuList>
            </AccordionDetails>
        </Accordion>
    );
}

export default memo(AssetAppearsIn);

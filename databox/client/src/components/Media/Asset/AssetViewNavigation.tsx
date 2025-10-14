import {Box, IconButton} from '@mui/material';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import {AssetContextState} from './assetTypes.ts';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';
import {modalRoutes, Routing} from '../../../routes.ts';

type Props = {
    currentId: string;
    state: AssetContextState | undefined;
};

export default function AssetViewNavigation({currentId, state}: Props) {
    const navigateToModal = useNavigateToModal();
    const {assetsContext} = state ?? {};
    if (!assetsContext) {
        return null;
    }

    const currentIndex = assetsContext.findIndex(t => t[0] === currentId);

    const goTo = (index: number) => {
        const [id, renditionId] = assetsContext[index];

        navigateToModal(
            modalRoutes.assets.routes.view,
            {
                id,
                renditionId: renditionId || Routing.UnknownRendition,
            },
            {state}
        );
    };

    return (
        <Box
            sx={{
                mr: 1,
                flexShrink: 0,
            }}
        >
            <IconButton
                disabled={currentIndex === 0}
                onClick={() => goTo(currentIndex - 1)}
            >
                <KeyboardArrowLeftIcon />
            </IconButton>
            <IconButton
                disabled={currentIndex === assetsContext.length - 1}
                onClick={() => goTo(currentIndex + 1)}
            >
                <KeyboardArrowRightIcon />
            </IconButton>
        </Box>
    );
}

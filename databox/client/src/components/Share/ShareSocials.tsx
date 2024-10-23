import {
    EmailIcon,
    EmailShareButton,
    FacebookIcon,
    FacebookShareButton,
    InstapaperIcon,
    InstapaperShareButton,
    LinkedinIcon,
    LinkedinShareButton,
    PinterestIcon,
    PinterestShareButton,
    PocketIcon,
    PocketShareButton,
    TelegramIcon,
    TelegramShareButton,
    TumblrIcon,
    TumblrShareButton,
    TwitterShareButton,
    WhatsappIcon,
    WhatsappShareButton,
    WorkplaceIcon,
    WorkplaceShareButton,
    XIcon,
} from 'react-share';
import {Box, IconButton} from '@mui/material';
import {useTranslation} from 'react-i18next';
import EmbedDialog, {EmbedProps} from './EmbedDialog.tsx';
import CodeIcon from '@mui/icons-material/Code';
import {useModals} from '@alchemy/navigation';

type Props = EmbedProps;

export default function ShareSocials(props: Props) {
    const {url, title, isImage} = props;
    const {openModal} = useModals();
    const iconSize = 32;
    const {t} = useTranslation();

    return (
        <>
            <Box
                sx={theme => ({
                    'mt': theme.spacing(1),
                    'display': 'flex',
                    'flexWrap': 'wrap',
                    'alignItems': 'center',
                    'direction': 'row',
                    'gap': theme.spacing(1),
                    'button svg': {
                        display: 'block',
                    },
                })}
            >
                <IconButton
                    title={t('share.socials.embed.title', 'Embed Share')}
                    onClick={() => {
                        openModal(EmbedDialog, props);
                    }}
                >
                    <CodeIcon />
                </IconButton>
                <EmailShareButton
                    htmlTitle={t('share.socials.email.title', 'Share by email')}
                    url={url}
                    subject={title}
                    body="body"
                >
                    <EmailIcon size={iconSize} round />
                </EmailShareButton>
                <FacebookShareButton
                    htmlTitle={t(
                        'share.socials.facebook.title',
                        'Share on Facebook'
                    )}
                    url={url}
                >
                    <FacebookIcon size={iconSize} round />
                </FacebookShareButton>
                <TwitterShareButton
                    htmlTitle={t(
                        'share.socials.twitter.title',
                        'Share on Twitter'
                    )}
                    url={url}
                    title={title}
                >
                    <XIcon size={iconSize} round />
                </TwitterShareButton>
                <LinkedinShareButton
                    htmlTitle={t(
                        'share.socials.linkedin.title',
                        'Share on LinkedIn'
                    )}
                    url={url}
                >
                    <LinkedinIcon size={iconSize} round />
                </LinkedinShareButton>
                <PinterestShareButton
                    htmlTitle={t(
                        'share.socials.pinterest.title',
                        'Share on Pinterest'
                    )}
                    url={url}
                    media={isImage ? url : url}
                >
                    <PinterestIcon size={iconSize} round />
                </PinterestShareButton>
                <TelegramShareButton
                    htmlTitle={t(
                        'share.socials.telegram.title',
                        'Share on Telegram'
                    )}
                    url={url}
                    title={title}
                >
                    <TelegramIcon size={iconSize} round />
                </TelegramShareButton>
                <WhatsappShareButton
                    htmlTitle={t(
                        'share.socials.whatsapp.title',
                        'Share on WhatsApp'
                    )}
                    url={url}
                    title={title}
                >
                    <WhatsappIcon size={iconSize} round />
                </WhatsappShareButton>
                <TumblrShareButton
                    htmlTitle={t(
                        'share.socials.tumblr.title',
                        'Share on Tumblr'
                    )}
                    url={url}
                    title={title}
                >
                    <TumblrIcon size={iconSize} round />
                </TumblrShareButton>
                <WorkplaceShareButton
                    htmlTitle={t(
                        'share.socials.workplace.title',
                        'Share on Workplace'
                    )}
                    url={url}
                    quote={title}
                >
                    <WorkplaceIcon size={iconSize} round />
                </WorkplaceShareButton>
                <PocketShareButton
                    htmlTitle={t(
                        'share.socials.pocket.title',
                        'Share on Pocket'
                    )}
                    url={url}
                    title={title}
                >
                    <PocketIcon size={iconSize} round />
                </PocketShareButton>
                <InstapaperShareButton
                    htmlTitle={t(
                        'share.socials.instapaper.title',
                        'Share on Instapaper'
                    )}
                    url={url}
                    title={title}
                >
                    <InstapaperIcon size={iconSize} round />
                </InstapaperShareButton>
            </Box>
        </>
    );
}

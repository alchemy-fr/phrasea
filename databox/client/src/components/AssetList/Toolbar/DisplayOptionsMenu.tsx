import React, {useContext} from 'react';
import {
    Box,
    FormControlLabel,
    FormGroup,
    IconButton,
    Menu,
    Switch,
    Tooltip,
    Typography,
} from '@mui/material';
import {useTranslation} from 'react-i18next';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {DisplayContext} from '../../Media/DisplayContext.tsx';
import {debounce} from '../../../lib/debounce.ts';
import ToggleWithLimit from '../../Media/Search/ToggleWithLimit.tsx';
import ThumbSizeWidget from './ThumbSizeWidget.tsx';

type Props = {};

export default function DisplayOptionsMenu({}: Props) {
    const {t} = useTranslation();
    const {
        thumbSize,
        setThumbSize,
        displayTitle,
        toggleDisplayTitle,
        displayCollections,
        toggleDisplayCollections,
        titleRows,
        collectionsLimit,
        setCollectionsLimit,
        setTitleRows,
        playVideos,
        togglePlayVideos,
        displayTags,
        tagsLimit,
        toggleDisplayTags,
        setTagsLimit,
        displayPreview,
        toggleDisplayPreview,
    } = useContext(DisplayContext)!;

    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);
    const handleMoreClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleMoreClose = () => {
        setAnchorEl(null);
    };

    const onThumbSizeChange = debounce((v: number) => setThumbSize(v), 0);

    const sliderId = 'thumb_size-slider';
    const moreBtnId = 'more-button';

    return (
        <>
            <Tooltip title={t('layout.options.more', 'More options')}>
                <IconButton
                    id={moreBtnId}
                    aria-controls={menuOpen ? 'more-menu' : undefined}
                    aria-haspopup="true"
                    aria-expanded={menuOpen ? 'true' : undefined}
                    onClick={handleMoreClick}
                >
                    <ArrowDropDownIcon />
                </IconButton>
            </Tooltip>
            <Menu
                anchorEl={anchorEl}
                open={menuOpen}
                onClose={handleMoreClose}
                MenuListProps={{
                    'aria-labelledby': moreBtnId,
                }}
            >
                <Box
                    sx={{
                        px: 4,
                        py: 1,
                        width: {
                            md: 500,
                        },
                    }}
                >
                    <Typography id={sliderId} gutterBottom>
                        {t('layout.options.thumb_size.label', 'Thumbnail size')}
                    </Typography>
                    <ThumbSizeWidget
                        sliderId={sliderId}
                        onChange={onThumbSizeChange}
                        defaultValue={thumbSize}
                    />

                    <ToggleWithLimit
                        label={t(
                            'layout.options.display_title.label',
                            'Display title'
                        )}
                        unit={t('layout.options.title_rows.label', 'rows')}
                        value={displayTitle}
                        toggle={toggleDisplayTitle}
                        setLimit={setTitleRows}
                        limit={titleRows}
                    />
                    <ToggleWithLimit
                        label={t(
                            'layout.options.display_tags.label',
                            'Display tags'
                        )}
                        unit={t('layout.options.tags_count.label', 'tags')}
                        value={displayTags}
                        toggle={toggleDisplayTags}
                        setLimit={setTagsLimit}
                        limit={tagsLimit}
                    />
                    <ToggleWithLimit
                        label={t(
                            'layout.options.display_collections.label',
                            'Display collections'
                        )}
                        unit={t(
                            'layout.options.collections_count.label',
                            'collections'
                        )}
                        value={displayCollections}
                        toggle={toggleDisplayCollections}
                        setLimit={setCollectionsLimit}
                        limit={collectionsLimit}
                    />
                    <FormGroup>
                        <FormControlLabel
                            control={
                                <Switch
                                    checked={displayPreview}
                                    onChange={toggleDisplayPreview}
                                />
                            }
                            label={t(
                                'layout.options.display_previews_hover.label',
                                'Display preview on hover'
                            )}
                        />
                    </FormGroup>
                    {displayPreview && (
                        <FormGroup>
                            <FormControlLabel
                                control={
                                    <Switch
                                        checked={playVideos}
                                        onChange={togglePlayVideos}
                                    />
                                }
                                label={t(
                                    'layout.options.play_preview_videos.label',
                                    'Auto play video previews'
                                )}
                            />
                        </FormGroup>
                    )}
                </Box>
            </Menu>
        </>
    );
}

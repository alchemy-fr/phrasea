import React, {useCallback, useContext} from 'react';
import {
    Box,
    FormControlLabel,
    FormGroup,
    Menu,
    Switch,
    Tooltip,
    Typography,
} from '@mui/material';
import {useTranslation} from 'react-i18next';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {DisplayContext, PreviewOptions} from '../../Media/DisplayContext';
import {debounce} from '../../../lib/debounce';
import ToggleWithLimit from '../../Media/Search/ToggleWithLimit';
import ThumbSizeWidget from './ThumbSizeWidget';
import SizeRatioWidget from './SizeRatioWidget.tsx';
import {StateSetter} from '../../../types.ts';
import AttributeListSwitcher from '../../AttributeList/AttributeListSwitcher.tsx';
import SettingsIcon from '@mui/icons-material/Settings';
import Button from '@mui/material/Button';

type Props = {};

export default function DisplayOptionsMenu({}: Props) {
    const {t} = useTranslation();
    const {
        state: {
            thumbSize,
            displayTitle,
            displayCollections,
            titleRows,
            collectionsLimit,
            playVideos,
            displayTags,
            tagsLimit,
            displayPreview,
            previewOptions,
        },
        setState: setDisplayPreferences,
    } = useContext(DisplayContext)!;

    const setPreviewOptions = useCallback<StateSetter<PreviewOptions>>(
        handler => {
            setDisplayPreferences(p => ({
                ...p,
                previewOptions: {
                    ...p.previewOptions,
                    ...(typeof handler === 'function'
                        ? handler(p.previewOptions)
                        : handler),
                },
            }));
        },
        [setDisplayPreferences]
    );

    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);
    const handleMoreClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleMoreClose = () => {
        setAnchorEl(null);
    };

    const onThumbSizeChange = debounce(
        (v: number) =>
            setDisplayPreferences(p => ({
                ...p,
                thumbSize: v,
            })),
        0
    );

    const sliderId = 'thumb_size-slider';
    const moreBtnId = 'more-button';

    return (
        <>
            <Tooltip title={t('layout.options.more', 'More options')}>
                <Button
                    id={moreBtnId}
                    aria-controls={menuOpen ? 'more-menu' : undefined}
                    aria-haspopup="true"
                    aria-expanded={menuOpen ? 'true' : undefined}
                    onClick={handleMoreClick}
                >
                    <SettingsIcon />
                    <ArrowDropDownIcon />
                </Button>
            </Tooltip>
            <Menu
                anchorEl={anchorEl}
                open={menuOpen}
                onClose={handleMoreClose}
                MenuListProps={{
                    'aria-labelledby': moreBtnId,
                }}
            >
                <Box sx={{p: 2}}>
                    <AttributeListSwitcher />
                </Box>
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
                        toggle={() => {
                            setDisplayPreferences(p => ({
                                ...p,
                                displayTitle: !p.displayTitle,
                            }));
                        }}
                        setLimit={v => {
                            setDisplayPreferences(p => ({
                                ...p,
                                titleRows: v,
                            }));
                        }}
                        limit={titleRows}
                    />
                    <ToggleWithLimit
                        label={t(
                            'layout.options.display_tags.label',
                            'Display tags'
                        )}
                        unit={t('layout.options.tags_count.label', 'tags')}
                        value={displayTags}
                        toggle={() => {
                            setDisplayPreferences(p => ({
                                ...p,
                                displayTags: !p.displayTags,
                            }));
                        }}
                        setLimit={v => {
                            setDisplayPreferences(p => ({
                                ...p,
                                tagsLimit: v,
                            }));
                        }}
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
                        toggle={() => {
                            setDisplayPreferences(p => ({
                                ...p,
                                displayCollections: !p.displayCollections,
                            }));
                        }}
                        setLimit={v => {
                            setDisplayPreferences(p => ({
                                ...p,
                                collectionsLimit: v,
                            }));
                        }}
                        limit={collectionsLimit}
                    />
                    <FormGroup>
                        <FormControlLabel
                            control={
                                <Switch
                                    checked={displayPreview}
                                    onChange={() => {
                                        setDisplayPreferences(p => ({
                                            ...p,
                                            displayPreview: !p.displayPreview,
                                        }));
                                    }}
                                />
                            }
                            label={t(
                                'layout.options.display_previews_hover.label',
                                'Display preview on hover'
                            )}
                        />
                    </FormGroup>
                    {displayPreview && (
                        <>
                            <Typography gutterBottom>
                                {t(
                                    'layout.options.preview_options.options.label',
                                    'Preview options'
                                )}
                            </Typography>
                            <FormGroup>
                                <FormControlLabel
                                    control={
                                        <Switch
                                            checked={playVideos}
                                            onChange={() => {
                                                setDisplayPreferences(p => ({
                                                    ...p,
                                                    playVideos: !p.playVideos,
                                                }));
                                            }}
                                        />
                                    }
                                    label={t(
                                        'layout.options.play_preview_videos.label',
                                        'Auto play video previews'
                                    )}
                                />
                            </FormGroup>
                            <FormGroup>
                                <FormControlLabel
                                    control={
                                        <Switch
                                            checked={previewOptions.displayFile}
                                            onChange={() => {
                                                setPreviewOptions(p => ({
                                                    ...p,
                                                    displayFile: !p.displayFile,
                                                }));
                                            }}
                                        />
                                    }
                                    label={t(
                                        'layout.options.preview_options.displayFile.label',
                                        'Display File in preview'
                                    )}
                                />
                            </FormGroup>
                            <FormGroup>
                                <FormControlLabel
                                    control={
                                        <Switch
                                            checked={
                                                previewOptions.displayAttributes
                                            }
                                            onChange={() => {
                                                setPreviewOptions(p => ({
                                                    ...p,
                                                    displayAttributes:
                                                        !p.displayAttributes,
                                                }));
                                            }}
                                        />
                                    }
                                    label={t(
                                        'layout.options.preview_options.displayAttributes.label',
                                        'Display attributes in preview'
                                    )}
                                />
                            </FormGroup>
                            <FormGroup>
                                <Typography gutterBottom>
                                    {t(
                                        'layout.options.preview_options.sizeRatio.label',
                                        'Size'
                                    )}
                                </Typography>
                                <SizeRatioWidget
                                    min={20}
                                    max={80}
                                    sliderId={'sizeRatio'}
                                    defaultValue={previewOptions.sizeRatio}
                                    onChange={v => {
                                        setPreviewOptions(p => ({
                                            ...p,
                                            sizeRatio: v,
                                        }));
                                    }}
                                />
                            </FormGroup>
                            {previewOptions.displayAttributes &&
                                previewOptions.displayFile && (
                                    <FormGroup>
                                        <Typography gutterBottom>
                                            {t(
                                                'layout.options.preview_options.attributesRatio.label',
                                                'Attributes Size'
                                            )}
                                        </Typography>
                                        <SizeRatioWidget
                                            min={20}
                                            max={80}
                                            sliderId={'attributesRatio'}
                                            defaultValue={
                                                previewOptions.attributesRatio
                                            }
                                            onChange={v => {
                                                setPreviewOptions(p => ({
                                                    ...p,
                                                    attributesRatio: v,
                                                }));
                                            }}
                                        />
                                    </FormGroup>
                                )}
                        </>
                    )}
                </Box>
            </Menu>
        </>
    );
}

import React, {useContext} from 'react';
import {
    Box,
    Checkbox,
    FormControlLabel,
    FormGroup,
    Grid,
    IconButton, Input, InputAdornment,
    Menu,
    Slider, Switch,
    Tooltip,
    Typography
} from "@mui/material";
import {useTranslation} from "react-i18next";
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {DisplayContext} from "../DisplayContext";
import {debounce} from "../../../lib/debounce";
import PhotoSizeSelectLargeIcon from '@mui/icons-material/PhotoSizeSelectLarge';
import PhotoSizeSelectActualIcon from '@mui/icons-material/PhotoSizeSelectActual';

type Props = {};

export default function DisplayOptionsMenu({}: Props) {
    const {t} = useTranslation();
    const {
        thumbSize,
        setThumbSize,
        displayTitle,
        toggleDisplayTitle,
        titleRows,
        setTitleRows,
    } = useContext(DisplayContext)!;

    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);
    const handleMoreClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleMoreClose = () => {
        setAnchorEl(null);
    };

    const onChange = debounce((e, v) => setThumbSize(v as number), 10);

    const max = 400;
    const min = 60;

    const sliderId = "thumb_size-slider";
    const moreBtnId = "more-button";

    return <>
        <Tooltip title={t('layout.options.more', 'More options')}>
            <IconButton
                id={moreBtnId}
                aria-controls={menuOpen ? 'more-menu' : undefined}
                aria-haspopup="true"
                aria-expanded={menuOpen ? 'true' : undefined}
                onClick={handleMoreClick}
            >
                <ArrowDropDownIcon/>
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
                        md: 500
                    }
                }}
            >
                <Typography id={sliderId} gutterBottom>
                    {t('layout.options.thumb_size.label', 'Thumbnail size')}
                </Typography>
                <Grid container spacing={2} alignItems="center">
                    <Grid item>
                        <PhotoSizeSelectLargeIcon/>
                    </Grid>
                    <Grid item xs>
                        <Slider
                            max={max}
                            min={min}
                            defaultValue={thumbSize}
                            aria-labelledby={sliderId}
                            valueLabelDisplay="auto"
                            onChange={onChange}
                        />
                    </Grid>
                    <Grid item>
                        <PhotoSizeSelectActualIcon/>
                    </Grid>
                </Grid>
                <Grid container spacing={2} alignItems="center">
                    <Grid item>
                        <FormGroup>
                            <FormControlLabel
                                control={<Switch
                                    checked={displayTitle}
                                    onChange={() => toggleDisplayTitle()}
                                />}
                                label={t('layout.options.display_title.label', 'Display title')}
                            />
                        </FormGroup>
                    </Grid>

                    {displayTitle && <Grid item>
                        <Input
                            onChange={(e) => setTitleRows(parseInt(e.target.value))}
                            value={titleRows}
                            type={'number'}
                            inputProps={{
                                min: 1
                            }}
                            sx={theme => ({
                                input: {
                                    width: theme.spacing(5)
                                }
                            })}
                            endAdornment={<InputAdornment position={'end'}>
                                {t('layout.options.title_rows.label', 'rows')}
                            </InputAdornment>}
                            />
                    </Grid>}
                </Grid>
            </Box>
        </Menu>
    </>
}

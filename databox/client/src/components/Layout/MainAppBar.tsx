import React, {ChangeEvent, useContext} from 'react';
import {alpha, makeStyles} from '@material-ui/core/styles';
import AppBar from '@material-ui/core/AppBar';
import Toolbar from '@material-ui/core/Toolbar';
import IconButton from '@material-ui/core/IconButton';
import Typography from '@material-ui/core/Typography';
import InputBase from '@material-ui/core/InputBase';
import MenuItem from '@material-ui/core/MenuItem';
import Menu from '@material-ui/core/Menu';
import MenuIcon from '@material-ui/icons/Menu';
import SearchIcon from '@material-ui/icons/Search';
import AccountCircle from '@material-ui/icons/AccountCircle';
import MoreIcon from '@material-ui/icons/MoreVert';
import useScrollTrigger from "@material-ui/core/useScrollTrigger";
import {Button, CssBaseline, Slide} from "@material-ui/core";
import {useHistory, useLocation} from "react-router-dom";
import {SelectionContext} from "../Media/SelectionContext";

interface HideProps {
    /**
     * Injected by the documentation to work in an iframe.
     * You won't need it on your project.
     */
    window?: () => Window;
    children: React.ReactElement;
}

function HideOnScroll(props: HideProps) {
    const {children, window} = props;
    // Note that you normally won't need to set the window ref as useScrollTrigger
    // will default to window.
    // This is only being set here because the demo is in an iframe.
    const trigger = useScrollTrigger({target: window ? window() : undefined});

    return (
        <Slide appear={false} direction="down" in={!trigger}>
            {children}
        </Slide>
    );
}

const useStyles = makeStyles((theme) => ({
    grow: {
        flexGrow: 1,
    },
    menuButton: {
        marginRight: theme.spacing(2),
    },
    title: {
        display: 'none',
        [theme.breakpoints.up('sm')]: {
            display: 'block',
        },
    },
    search: {
        position: 'relative',
        borderRadius: theme.shape.borderRadius,
        backgroundColor: alpha(theme.palette.common.white, 0.15),
        '&:hover': {
            backgroundColor: alpha(theme.palette.common.white, 0.25),
        },
        marginRight: theme.spacing(2),
        marginLeft: 0,
        width: '100%',
        [theme.breakpoints.up('sm')]: {
            marginLeft: theme.spacing(3),
            width: 'auto',
        },
    },
    searchIcon: {
        padding: theme.spacing(0, 2),
        height: '100%',
        position: 'absolute',
        pointerEvents: 'none',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
    },
    inputRoot: {
        color: 'inherit',
    },
    inputInput: {
        padding: theme.spacing(1, 1, 1, 0),
        // vertical padding + font size from searchIcon
        paddingLeft: `calc(1em + ${theme.spacing(4)}px)`,
        transition: theme.transitions.create('width'),
        width: '100%',
        [theme.breakpoints.up('md')]: {
            width: '20ch',
            '&:focus': {
                width: '50ch',
            }
        },
    },
    sectionDesktop: {
        display: 'none',
        [theme.breakpoints.up('md')]: {
            display: 'flex',
        },
    },
    sectionMobile: {
        display: 'flex',
        [theme.breakpoints.up('md')]: {
            display: 'none',
        },
    },
}));

type Props = {
    title: string;
    username?: string;
    onLogout: (e: React.MouseEvent<HTMLElement>) => void;
    searchQuery?: string;
    onSearchQueryChange: Function;
    toggleMenu: (e: React.MouseEvent) => void;
}

export default function MainAppBar(props: Props) {
    const selectionContext = useContext(SelectionContext);

    const onTitleClick = () => selectionContext!.selectWorkspace(undefined, true);

    const classes = useStyles();
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const [mobileMoreAnchorEl, setMobileMoreAnchorEl] = React.useState<null | HTMLElement>(null);

    const isMenuOpen = Boolean(anchorEl);
    const isMobileMenuOpen = Boolean(mobileMoreAnchorEl);

    const handleProfileMenuOpen = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorEl(event.currentTarget);
    };

    const handleMobileMenuClose = () => {
        setMobileMoreAnchorEl(null);
    };

    const handleMenuClose = () => {
        setAnchorEl(null);
        handleMobileMenuClose();
    };

    const handleMobileMenuOpen = (event: React.MouseEvent<HTMLElement>) => {
        setMobileMoreAnchorEl(event.currentTarget);
    };

    const authenticated = !!props.username;
    const history = useHistory();
    const location = useLocation();

    const menuId = 'primary-search-account-menu';
    const renderMenu = (
        <Menu
            anchorEl={anchorEl}
            anchorOrigin={{vertical: 'top', horizontal: 'right'}}
            id={menuId}
            keepMounted
            transformOrigin={{vertical: 'top', horizontal: 'right'}}
            open={isMenuOpen}
            onClose={handleMenuClose}
        >
            <MenuItem onClick={handleMenuClose}>{props.username}</MenuItem>
            <MenuItem onClick={props.onLogout}>Logout</MenuItem>
        </Menu>
    );

    const mobileMenuId = 'primary-search-account-menu-mobile';
    const renderMobileMenu = (
        <Menu
            anchorEl={mobileMoreAnchorEl}
            anchorOrigin={{vertical: 'top', horizontal: 'right'}}
            id={mobileMenuId}
            keepMounted
            transformOrigin={{vertical: 'top', horizontal: 'right'}}
            open={isMobileMenuOpen}
            onClose={handleMobileMenuClose}
        >
            <MenuItem onClick={handleMenuClose}>
                {props.username}
            </MenuItem>
            <MenuItem onClick={props.onLogout}>Logout</MenuItem>
        </Menu>
    );

    const [searchValue, setSearchValue] = React.useState<string | undefined>(props.searchQuery);

    const onSearchQueryChange = (e: ChangeEvent<HTMLInputElement>): void => {
        setSearchValue(e.target.value);
    }

    const onSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            doSearch();
        }
    }

    const doSearch = (): void => {
        props.onSearchQueryChange(searchValue);
    }

    return (
        <>
            <CssBaseline/>
            <HideOnScroll {...props}>
                <AppBar>
                    <Toolbar>
                        <IconButton
                            edge="start"
                            className={classes.menuButton}
                            color="inherit"
                            aria-label="open drawer"
                            onClick={props.toggleMenu}
                        >
                            <MenuIcon/>
                        </IconButton>
                        <Typography
                            onClick={onTitleClick}
                            style={{
                                cursor: 'pointer'
                            }}
                            className={classes.title} variant="h6" noWrap>
                            {props.title}
                        </Typography>
                        <div className={classes.search}>
                            <div className={classes.searchIcon}>
                                <SearchIcon/>
                            </div>
                            <InputBase
                                placeholder="Search…"
                                classes={{
                                    root: classes.inputRoot,
                                    input: classes.inputInput,
                                }}
                                inputProps={{'aria-label': 'search'}}
                                onChange={onSearchQueryChange}
                                onKeyDown={onSearchKeyDown}
                                value={searchValue}
                            />
                            {searchValue && searchValue !== props.searchQuery && <Button
                                onClick={doSearch}
                                variant={"contained"}
                            >
                                Search
                            </Button>}
                        </div>
                        <div className={classes.grow}/>
                        <div className={classes.sectionDesktop}>
                            {/*<IconButton aria-label="show 0 new notifications" color="inherit">*/}
                            {/*    <Badge badgeContent={0} color="secondary">*/}
                            {/*        <NotificationsIcon/>*/}
                            {/*    </Badge>*/}
                            {/*</IconButton>*/}
                            {authenticated ? <IconButton
                                edge="end"
                                aria-label="account of current user"
                                aria-controls={menuId}
                                aria-haspopup="true"
                                onClick={handleProfileMenuOpen}
                                color="inherit"
                            >
                                <AccountCircle/>
                            </IconButton> : <MenuItem onClick={() => history.push({
                                pathname: '/login',
                                state: {from: location}
                            })}>Sign in</MenuItem>}
                        </div>
                        <div className={classes.sectionMobile}>
                            <IconButton
                                aria-label="show more"
                                aria-controls={mobileMenuId}
                                aria-haspopup="true"
                                onClick={handleMobileMenuOpen}
                                color="inherit"
                            >
                                <MoreIcon/>
                            </IconButton>
                        </div>
                    </Toolbar>
                </AppBar>
            </HideOnScroll>
            <Toolbar/>
            {renderMobileMenu}
            {renderMenu}
        </>
    );
}

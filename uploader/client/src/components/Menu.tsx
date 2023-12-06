import React, {useContext} from 'react';
import UserInfo from "./UserInfo";
import Languages from "./Languages";
import {useKeycloakUser} from '@alchemy/auth';
import {getPath, Link} from '@alchemy/navigation';
import UploaderUserContext from "../context/UploaderUserContext";
import {slide as Slide} from 'react-burger-menu';
import {routes} from "../routes.ts";

type Props = {};

export default function Menus({}: Props) {
    const {user, isAuthenticated, logout} = useKeycloakUser();
    const {uploaderUser} = useContext(UploaderUserContext);
    const [open, setOpen] = React.useState(false);

    const close = React.useCallback(() => {
        setOpen(false)
    }, [setOpen]);
    const onStateChange = React.useCallback(() => {
        setOpen(p => !p);
    }, [setOpen]);

    const perms = uploaderUser?.permissions;

    return <Slide
        pageWrapId="page-wrap"
        isOpen={open}
        onStateChange={onStateChange}
    >
        {user && <UserInfo email={user.username}/>}
        <Link
            onClick={close}
            to="/"
            className="menu-item"
        >
            Home
        </Link>
        {perms?.form_schema && <Link
                onClick={close}
                to={getPath(routes.admin.routes.formEditor)}
            >
                Form editor
            </Link>}
        {perms?.target_data && <Link
                onClick={close}
                to={getPath(routes.admin.routes.targetDataEditor)}
            >
                Target data editor
            </Link>}
        {isAuthenticated() && <a
            onClick={() => logout()}
        >
            Logout
        </a>}
        <Languages/>
    </Slide>
}

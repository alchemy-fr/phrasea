import React, {useContext} from 'react';
import {useParams} from "react-router";
import Publication from "../component/Publication.jsx";
import {AuthenticationContext} from "@alchemy/auth";

type Props = {};

export default function PublicationPage({}: Props) {
    const {id} = useParams();
const {user} = useContext(AuthenticationContext);

    return <Publication
        id={id}
        username={user?.preferred_username}
    />
}
import React, {PureComponent} from 'react';
import config from './config';
import {KeycloakClient} from '@alchemy/auth';
// import PropTypes from "prop-types";

export const keycloakClient = new KeycloakClient({
    clientId: config.clientId,
    baseUrl: config.keycloakUrl,
    realm: config.realmName,
});

export const oauthClient = keycloakClient.client;

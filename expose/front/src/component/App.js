import React from 'react';
import {Route, BrowserRouter as Router, Switch} from "react-router-dom";
import PublicationRoute from "./routes/PublicationRoute";
import PublicationIndex from "./index/PublicationIndex";
import AssetRoute from "./routes/AssetRoute";
import {getAuthRedirect, oauthClient, unsetAuthRedirect} from "../lib/oauth";
import {OAuthRedirect} from '@alchemy-fr/phraseanet-react-components';

function App() {
    return <Router>
        <Switch>
            <Route path="/auth/:provider" component={props => {
                return <OAuthRedirect
                    {...props}
                    oauthClient={oauthClient}
                    successHandler={(history) => {
                        history.replace(getAuthRedirect());
                        unsetAuthRedirect();
                    }}
                />
            }}/>
            <Route path="/" exact component={PublicationIndex} />
            <Route path="/:publication" exact component={PublicationRoute} />
            <Route path="/:publication/:asset" exact component={AssetRoute} />
            <Route path="/:publication/:asset/:subdef" exact component={AssetRoute} />
        </Switch>
    </Router>
}

export default App;

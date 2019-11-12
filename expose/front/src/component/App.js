import React from 'react';
import {Route, BrowserRouter as Router, Switch} from "react-router-dom";
import PublicationRoute from "./routes/PublicationRoute";
import PublicationIndex from "./index/PublicationIndex";
import AssetRoute from "./routes/AssetRoute";

function App() {
    return <Router>
        <Switch>
            <Route path="/" exact component={PublicationIndex} />
            <Route path="/:publication" exact component={PublicationRoute} />
            <Route path="/:publication/:asset" exact component={AssetRoute} />
            <Route path="/:publication/:asset/:subdef" exact component={AssetRoute} />
        </Switch>
    </Router>
}

export default App;

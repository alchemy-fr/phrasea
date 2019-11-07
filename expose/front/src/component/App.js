import React from 'react';
import {Route, BrowserRouter as Router} from "react-router-dom";
import PublicationRoute from "./routes/PublicationRoute";
import PublicationIndex from "./index/PublicationIndex";

function App() {
    return <Router>
        <Route path="/" exact component={PublicationIndex} />
        <Route path="/p/:id" exact component={PublicationRoute} />
    </Router>
}

export default App;

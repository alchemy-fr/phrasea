import React, {PureComponent} from 'react';
import AssetGrid from "./Media/AssetGrid";
import {oauthClient} from "../oauth";
import config from "../config";
import CollectionsPanel from "./Media/CollectionsPanel";
import MediaSelection from "./Media/MediaSelection";

export default class App extends PureComponent {
    logout = () => {
        oauthClient.logout();
        if (!config.isDirectLoginForm()) {
            document.location.href = `${config.getAuthBaseUrl()}/security/logout`;
        }
    }

    render() {
        return <div className="App">
            <nav className="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
                <div className="navbar-brand col-sm-3 col-md-2 mr-0">Databox Client.</div>
                <input className="form-control form-control-dark w-100" type="text" placeholder="Search"
                       aria-label="Search"/>
                <ul className="navbar-nav px-3">
                    <li className="nav-item text-nowrap">
                        {}
                    </li>
                    <li className="nav-item text-nowrap">
                        <a className="nav-link"
                           onClick={this.logout}
                        >Sign out</a>
                    </li>
                </ul>
            </nav>

            <MediaSelection>
                <div className="container-fluid">
                    <div className="row">
                        <nav className="col-md-2 d-none d-md-block bg-light sidebar">
                            <CollectionsPanel/>
                        </nav>

                        <main role="main" className="col-md-9 ml-sm-auto col-lg-10 pt-3 px-4">
                            <div>
                                <AssetGrid
                                    query={'toto'}
                                />
                            </div>
                        </main>
                    </div>
                </div>
            </MediaSelection>
        </div>
    }
}

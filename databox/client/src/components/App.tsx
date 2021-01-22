import React, {PureComponent} from 'react';
import AssetGrid from "./Media/AssetGrid";
import {oauthClient} from "../oauth";
import config from "../config";
import CollectionsPanel from "./Media/CollectionsPanel";

interface Collection {
    title: string;
}

export default class App extends PureComponent {
    logout = () => {
        oauthClient.logout();
        if (!config.isDirectLoginForm()) {
            document.location.href = `${config.getAuthBaseUrl()}/security/logout`;
        }
    }

    render() {
        const collections: Collection[] = [
            {title: 'Collection #1'},
            {title: 'Collection #2'},
            {title: 'Collection #3'},
            {title: 'Collection #4'},
        ];

        return <div className="App">
            <nav className="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
                <div className="navbar-brand col-sm-3 col-md-2 mr-0">Databox Client.</div>
                <input className="form-control form-control-dark w-100" type="text" placeholder="Search"
                       aria-label="Search"/>
                <ul className="navbar-nav px-3">
                    <li className="nav-item text-nowrap">
                        <a className="nav-link"
                            onClick={this.logout}
                        >Sign out</a>
                    </li>
                </ul>
            </nav>

            <div className="container-fluid">
                <div className="row">
                    <nav className="col-md-2 d-none d-md-block bg-light sidebar">
                        <div className="sidebar-sticky">
                            <ul className="nav flex-column">
                                <CollectionsPanel />
                            </ul>
                        </div>
                    </nav>

                    <main role="main" className="col-md-9 ml-sm-auto col-lg-10 pt-3 px-4">
                        <div
                            className="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                            <h1 className="h2">Dashboard</h1>
                            <div className="btn-toolbar mb-2 mb-md-0">
                                <div className="btn-group mr-2">
                                    <button className="btn btn-sm btn-outline-secondary">Share</button>
                                    <button className="btn btn-sm btn-outline-secondary">Export</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <AssetGrid
                                query={'toto'}
                            />
                        </div>
                    </main>
                </div>
            </div>
        </div>
    }
}

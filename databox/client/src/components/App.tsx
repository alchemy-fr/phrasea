import React, {ChangeEvent, PureComponent} from 'react';
import AssetGrid from "./Media/AssetGrid";
import {oauthClient} from "../oauth";
import config from "../config";
import CollectionsPanel from "./Media/CollectionsPanel";
import MediaSelection from "./Media/MediaSelection";
import {UserContext} from "./Security/UserContext";

type State = {
    searchQuery: string;
}

export default class App extends PureComponent<{}, State> {
    static contextType = UserContext;
    context: React.ContextType<typeof UserContext>;

    state: State = {
        searchQuery: '',
    }

    logout = () => {
        oauthClient.logout();
        if (!config.isDirectLoginForm()) {
            document.location.href = `${config.getAuthBaseUrl()}/security/logout`;
        }
    }

    onSearchQueryChange: React.ChangeEventHandler<HTMLInputElement> = (e) => {
        this.setState({searchQuery: e.target.value});
    }

    render() {
        return <div className="App">
            <nav className="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
                <div className="navbar-brand col-sm-3 col-md-2 mr-0">
                    Databox Client.
                </div>
                <input className="form-control form-control-dark w-100"
                       type="text"
                       onChange={this.onSearchQueryChange}
                       value={this.state.searchQuery}
                       placeholder="Search"
                       aria-label="Search"/>
                <ul className="navbar-nav px-3">
                    <li className="nav-item text-nowrap">
                        <a className={'nav-link'}>
                            {this.context.user ? this.context.user.username : ''}
                        </a>
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
                                    query={this.state.searchQuery}
                                />
                            </div>
                        </main>
                    </div>
                </div>
            </MediaSelection>
        </div>
    }
}

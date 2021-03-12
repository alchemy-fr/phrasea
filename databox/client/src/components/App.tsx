import React, {PureComponent} from 'react';
import AssetGrid from "./Media/AssetGrid";
import {oauthClient} from "../oauth";
import config from "../config";
import CollectionsPanel from "./Media/CollectionsPanel";
import MediaSelection from "./Media/MediaSelection";
import {UserContext} from "./Security/UserContext";
import MainAppBar from "./Layout/MainAppBar";

type State = {
    searchQuery: string;
    hideMenu: boolean;
}

export default class App extends PureComponent<{}, State> {
    static contextType = UserContext;
    context: React.ContextType<typeof UserContext>;

    state: State = {
        searchQuery: '',
        hideMenu: false,
    }

    logout = () => {
        oauthClient.logout();
        if (!config.isDirectLoginForm()) {
            document.location.href = `${config.getAuthBaseUrl()}/security/logout`;
        }
    }

    onSearchQueryChange = (value: string) => {
        this.setState({searchQuery: value});
    }

    toggleMenu = () => {
        this.setState(prevState => ({
            hideMenu: !prevState.hideMenu,
        }))
    }

    render() {
        return <>
            <MainAppBar
                toggleMenu={this.toggleMenu}
                title={'Databox Client.'}
                onLogout={this.logout}
                username={this.context.user ? this.context.user.username : undefined}
                onSearchQueryChange={this.onSearchQueryChange}
                searchQuery={this.state.searchQuery}
            />
            <MediaSelection>
                <div className="main-layout">
                    {!this.state.hideMenu && <div className="main-left-menu">
                        <CollectionsPanel/>
                    </div>}
                    <div className="main-content">
                        <AssetGrid
                            query={this.state.searchQuery}
                        />
                    </div>
                </div>
            </MediaSelection>
        </>
    }
}

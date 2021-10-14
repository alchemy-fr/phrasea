import React, {PureComponent} from 'react';
import AssetGrid from "./Media/AssetGrid";
import {oauthClient} from "../oauth";
import config from "../config";
import CollectionsPanel from "./Media/CollectionsPanel";
import MediaSelection from "./Media/MediaSelection";
import {UserContext} from "./Security/UserContext";
import MainAppBar from "./Layout/MainAppBar";
import Dropzone from "react-dropzone";
import {UploadFile, UploadFiles} from "../api/file";

type State = {
    searchQuery: string;
    hideMenu: boolean;
}

type Props = {};

export default class App extends PureComponent<Props, State> {
    static contextType = UserContext;
    context: React.ContextType<typeof UserContext>;

    state: State = {
        searchQuery: '',
        hideMenu: false,
    }

    logout = () => {
        oauthClient.logout();
        if (!config.isDirectLoginForm()) {
            document.location.href = `${config.getAuthBaseUrl()}/security/logout?r=${encodeURIComponent(document.location.origin)}`;
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
        const authenticated = Boolean(this.context.user);

        return <>
            <MainAppBar
                toggleMenu={this.toggleMenu}
                title={'Databox Client.'}
                onLogout={this.logout}
                username={this.context.user ? this.context.user.username : undefined}
                onSearchQueryChange={this.onSearchQueryChange}
                searchQuery={this.state.searchQuery}
            />
            <Dropzone
                onDrop={this.onFileDrop}
            >
                {({getRootProps, getInputProps}) => (
                    <div {...getRootProps()}>
                        <input
                            {...getInputProps()}
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
                    </div>
                )}
            </Dropzone>
        </>
    }

    onFileDrop = async (acceptedFiles: File[]) => {
        const authenticated = Boolean(this.context.user);

        if (!authenticated) {
            window.alert('You must be authenticated in order to upload new files');
            return;
        }

        await UploadFiles(this.context.user!.id, acceptedFiles);
    }
}

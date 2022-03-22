import React, {useContext, useState} from 'react';
import {oauthClient} from "../oauth";
import config from "../config";
import AssetSelectionProvider from "./Media/AssetSelectionProvider";
import {UserContext} from "./Security/UserContext";
import MainAppBar from "./Layout/MainAppBar";
import LeftPanel from "./Media/LeftPanel";
import SearchContextProvider from "./Media/Search/SearchContextProvider";
import AssetResults from "./Media/Search/AssetResults";
import {HTML5Backend} from "react-dnd-html5-backend";
import {DndProvider} from "react-dnd";
import SearchFiltersProvider from "./Media/Search/SearchFiltersProvider";
import AssetDropzone from "./Media/Asset/AssetDropzone";

export default function App() {
    const [menuOpen, setMenuOpen] = useState(true);
    const user = useContext(UserContext);

    const logout = () => {
        oauthClient.logout();
        if (!config.isDirectLoginForm()) {
            document.location.href = `${config.getAuthBaseUrl()}/security/logout?r=${encodeURIComponent(document.location.origin)}`;
        }
    }

    const toggleMenu = () => setMenuOpen(open => !open);

    return <>
        <SearchFiltersProvider>
            <SearchContextProvider>
                <AssetDropzone>
                    <MainAppBar
                        toggleMenu={toggleMenu}
                        title={'Databox Client.'}
                        onLogout={logout}
                        username={user.user ? user.user.username : undefined}
                    />
                    <AssetSelectionProvider>
                        <DndProvider backend={HTML5Backend}>
                            <div className="main-layout">
                                {menuOpen && <div className="main-left-menu">
                                    <LeftPanel/>
                                </div>}
                                <div className="main-content">
                                    <AssetResults/>
                                </div>
                            </div>
                        </DndProvider>
                    </AssetSelectionProvider>
                </AssetDropzone>
            </SearchContextProvider>
        </SearchFiltersProvider>
    </>
}

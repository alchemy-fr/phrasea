import React, {useContext, useEffect} from 'react';
import {IconButton} from "@mui/material";
import {useBrowserPosition} from "../../../hooks/useBrowserLocation";
import {SearchContext} from "./SearchContext";
import {ResultContext} from "./ResultContext";
import LocationOffIcon from '@mui/icons-material/LocationOff';
import LocationOnIcon from '@mui/icons-material/LocationOn';

type Props = {};

export default function GeoPointFilter({}: Props) {
    const search = useContext(SearchContext);
    const resultContext = useContext(ResultContext);
    const [enabled, setEnabled] = React.useState(!!search.geolocation);

    const {position} = useBrowserPosition(enabled);

    useEffect(() => {
        if (!enabled) {
            search.setGeoLocation(undefined);
        } else if (position && search) {
            search.setGeoLocation(`${position.latitude},${position.longitude}`);
        }
    }, [position, enabled]);

    const toggleEnabled = React.useCallback(() => {
        setEnabled(p => !p);
    }, []);

    return <>
        <IconButton
            sx={{mr: 1}}
            onClick={toggleEnabled}
            disabled={resultContext.loading}
        >
            {enabled ? <LocationOnIcon/> : <LocationOffIcon/>}
        </IconButton>
    </>
}

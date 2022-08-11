import React, {useEffect, useState} from 'react';
import '../../scss/Upload.scss';
import {FullPageLoader} from "@alchemy-fr/phraseanet-react-components";
import {getTargets} from "../../requests";
import Container from "../Container";
import {Link} from "react-router-dom";
import Redirect from "react-router-dom/es/Redirect";

export default function SelectTarget() {
    const [targets, setTargets] = useState();

    useEffect(() => {
        getTargets().then(setTargets);
    }, []);

    if (!targets) {
        return <FullPageLoader/>
    }

    if (targets.length === 1) {
        return <Redirect to={`/upload/${targets[0].id}`}/>
    }

    return <Container>
        <div className={'row targets'}>
            {targets.map(t => {
                return <Link
                    key={t.id}
                    to={`/upload/${t.id}`}
                    className={'col-md-6 col-sm-12 target'}
                >
                    <span className={'target-box'}>
                        <span className={'target-title'}>
                            {t.name}
                        </span>
                        {t.description && <p className={'target-desc'}>{t.description}</p>}
                    </span>
                </Link>
            })}
        </div>
    </Container>
}

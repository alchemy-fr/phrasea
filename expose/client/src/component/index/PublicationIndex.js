import React, {PureComponent} from 'react';
import {Link} from "react-router-dom";
import Description from "../layouts/shared-components/Description";
import moment from "moment";
import {ReactComponent as SortImg} from "../../images/sort.svg";
import {Dropdown, DropdownButton} from "react-bootstrap";
import {Logo} from "../Logo";
import {Translation} from "react-i18next";
import FullPageLoader from "../FullPageLoader";
import {getThumbPlaceholder} from "../layouts/shared-components/placeholders";
import apiClient from "../../lib/api-client";

const SORT_BY_DATE = 'date';
const SORT_BY_NAME = 'name';
const orders = {
    [SORT_BY_DATE]: {
        label: `last_post_added`,
        query: `order[createdAt]=desc`,
    },
    [SORT_BY_NAME]: {
        label: `publication_name`,
        query: `order[title]=asc`,
    },
}

class PublicationIndex extends PureComponent {
    state = {
        data: null,
        sortBy: SORT_BY_DATE,
    };

    componentDidMount() {
        this.load();
    }

    load() {
        apiClient
            .get(`/publications?${orders[this.state.sortBy].query}`)
            .then((res) => {
                this.setState({data: res.data['hydra:member']});
            });
    }

    render() {
        const {data} = this.state;

        return <div className="container">
            <h1>
                <Logo />
            </h1>
            <div className="filters">
                <div className="sort">
                    <DropdownButton id="dropdown-basic-button" title={<>
                        <SortImg
                            width={20}
                            height={20}
                        />
                        <Translation>
                            {(t) =>
                                t(`order.${orders[this.state.sortBy].label}`)
                            }
                        </Translation>
                    </>}
                    >
                        {Object.keys(orders).map((o) => {
                            return <Dropdown.Item
                                key={o}
                                onClick={this.sortBy.bind(this, o)}
                            ><Translation>
                                {(t) =>
                                    t(`order.${orders[o].label}`)
                                }
                            </Translation></Dropdown.Item>
                        })}
                    </DropdownButton>
                </div>
            </div>
            <div>
                {data ? this.renderPublications() : <FullPageLoader/>}
            </div>
        </div>
    }

    sortBy(sortBy) {
        this.setState({
            sortBy,
            data: null,
        }, () => {
            this.load();
        });
    }

    renderPublications() {
        const {data} = this.state;

        return data.map(p => <div
            className={'publication-item'}
            key={p.id}
        >
            <Link to={`/${p.slug || p.id}`}>
                <div className="media">
                    <img
                        src={p.cover ? (p.cover.thumbUrl || getThumbPlaceholder(p.cover.mimeType)) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAARMAAAC3CAMAAAAGjUrGAAAAKlBMVEXg4OD////j4+Pb29v7+/vi4uLx8fHs7Oz39/f09PTa2tru7u7m5ubX19cF3ejnAAABRElEQVR4nO3Z27JDMBiAURFVVN//dXfp+ZC6Y0//tS4zphPflARVBQAAAAAAAAAAAAAAAAAAAAAAAADAj6i/23p6G+jSkn7rKa6tXUyS0mHrSa4rp9QuHNKlJq8yl//i1GS/cEgbtsm4Kx0StEme7ipd4ZCgTQ7zrbT7fOoxm+TL+vL58ondZLwPd/2tQ+wm99HTRu4WJWaTapyTtNdTz/Pe9holaJNcnxrsh+vYZbt/iRK0SVUdj8fnf8k9StgmDyMPD4VzlNBN5qU4Pz0nT1EiN2mmdSe/vDroQzdppsX4NUlKOXCT5ry9f3t3ErhJU3qfFLdJMUncJuUkYZvkchJNNJlo8u58P6l3JXXoPVtRxCZ9PX5Tx/u+4zvgu2H5e3E7LP/MbxmWLowhXBIAAAAAAAAAAAAAAAAAAAAAAAAAgF/1BxZSCIBLTls7AAAAAElFTkSuQmCC'}
                        className="mr-3"
                        alt={p.title}/>
                    <div className="media-body">
                        <h5 className="mt-0">
                            {p.title}
                        </h5>
                        {p.date ? <time>{moment(p.date).format('LLLL')}</time> : ''}
                        <Description
                            descriptionHtml={p.description}
                        />
                    </div>
                </div>
            </Link>
        </div>)
    }
}

export default PublicationIndex;

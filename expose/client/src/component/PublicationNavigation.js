import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Link} from "react-router-dom";
import {loadPublication} from "./Publication";

class PublicationNavigation extends PureComponent {
    static propTypes = {
        publication: PropTypes.object.isRequired,
    };

    render() {
        const {publication} = this.props;
        const {parent} = publication;

        return <div className={'pub-nav'}>
            {parent ? <div className={'nav-parent'}>
                <Link
                    className={'nav-item'}
                    to={`/${parent.slug || parent.id}`}>
                {parent.title}
            </Link>
            </div> : ''}
            <NavTree
                current={publication}
                depth={1}
                openChildren={true}
                publications={parent ? parent.children : [publication]}
            />
        </div>
    }
}

class NavTree extends PureComponent {
    static propTypes = {
        publications: PropTypes.array.isRequired,
        current: PropTypes.object.isRequired,
        depth: PropTypes.number.isRequired,
        openChildren: PropTypes.bool,
    };

    state = {
        publicationChildren: {},
        openPublications: {},
    }

    toggle(id) {
        this.loadChildren(id);

        this.setState(prevState => {
            const openPublications = {...prevState.openPublications};

            if (openPublications[id]) {
                delete openPublications[id];
            } else {
                openPublications[id] = true;
            }

            return {
                openPublications,
            }
        });
    }

    async loadChildren(id) {
        const res = await loadPublication(id);

        this.setState(prevState => {
            const publicationChildren = {...prevState.publicationChildren};

            publicationChildren[res.id] = res.children;

            return {publicationChildren};
        });
    }

    render() {
        const {current, publications, depth, openChildren} = this.props;
        const {openPublications, publicationChildren} = this.state;

        const baseNavClass = `nav-item nav-depth-${depth}`;

        return <ul className="pub-nav-ul list-unstyled components">
            {publications.map(c => {
                const p = typeof c === 'string' ? current : c;
                const children = p.children || publicationChildren[p.id];

                const navClass = `${baseNavClass}${p.id === current.id ? ' nav-current' : ''}`;

                const displayChildren = p.childrenCount && (
                    openPublications[p.id]
                    || (openChildren && p.id === current.id)
                );

                return <li
                    key={p.id}
                >
                    {p.id === current.id ? <div
                        className={navClass}
                    >
                        {p.title}
                    </div> : <Link
                        className={navClass}
                        to={`/${p.slug || p.id}`}
                    >
                        {p.title}
                    </Link>}
                    {!!displayChildren && <>
                        {children && children.length > 0 ?
                        <NavTree
                            publications={children}
                            current={current}
                            depth={depth + 1}
                        />
                        : <div className={navClass}>Loading...</div>}
                    </>}
                    {p.childrenCount > 0 && <div
                        className={'pub-nav-toggle'}
                        onClick={this.toggle.bind(this, p.id)}
                    >
                        {!!displayChildren ? '-' : '+'}
                    </div>}
                </li>
            })}
        </ul>
    }
}

export default PublicationNavigation;

import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {Link} from "react-router-dom";

class PublicationNavigation extends PureComponent {
    static propTypes = {
        publication: PropTypes.object.isRequired,
    };

    render() {
        const {publication} = this.props;
        const {parent} = publication;

        return <div className={'pub-nav'}>
            {parent ? <div className={'nav-parent'}>
                <Link to={`/${parent.slug || parent.id}`}>
                {parent.title}
            </Link>
            </div> : ''}
            <NavTree
                current={publication}
                publications={parent ? parent.children : [publication]}
            />
        </div>
    }
}

class NavTree extends PureComponent {
    static propTypes = {
        publications: PropTypes.array.isRequired,
        current: PropTypes.object.isRequired,
    };

    render() {
        const {current} = this.props;

        return <ul className="list-unstyled components">
            {this.props.publications.map(c => {
                const p = typeof c === 'string' ? current : c;


                return <li
                    key={p.id}
                >
                    {p.id === current.id ? <div>
                        {p.title}
                    </div> : <Link to={`/${p.slug || p.id}`}>
                        {p.title}
                    </Link>}
                    {p.children && p.children.length > 0 ?
                        <NavTree
                            publications={p.children}
                            current={current}
                        />
                        : ''}
                </li>
            })}
        </ul>
    }
}

export default PublicationNavigation;

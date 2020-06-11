import React, {Component} from 'react';
import {PropTypes} from 'prop-types';
import {dataShape} from "../props/dataShape";

class ThemeEditorProxy extends Component {
    static propTypes = {
        data: dataShape,
        render: PropTypes.func.isRequired,
    };

    state = {
        hidden: false,
    };

    static getDerivedStateFromProps(props, state) {
        const d = props.data || {};

        if (state.propsData === d) {
            return null;
        }

        return {
            propsData: props.data,
            theme: d.theme,
            layout: d.layout,
            lastPubId: d.id,
        };
    }

    hide = () => {
        this.setState({hidden: true});
    }

    render() {
        const {theme, layout} = this.state;
        const {data, render} = this.props;

        const body = window.document.body;
        body.className = body.className.replace(/\btheme-.+\b/g, "");
        body.classList.add(`theme-${theme}`);

        const subData = data ? {
            ...data,
            layout,
            theme,
        } : data;

        if (data && !this.state.hidden) {
            subData.editor = <div
                className={'theme-editor'}
            >
                <button
                    title={'Hide Editor'}
                    className={'btn btn-close btn-sm'}
                    onClick={this.hide}
                >
                    X
                </button>
                <h1>Display Editor</h1>
                <form>
                    <div className="form-group">
                        <label>Theme:</label>
                        <select
                            className={'form-control'}
                            onChange={e => this.setState({theme: e.target.value})}
                            value={theme || ''}
                        >
                            <option value="">None</option>
                            <option value="dark">Dark</option>
                            <option value="light">Light</option>
                        </select>
                    </div>
                    <div className="form-group">
                        <label>Layout:</label>
                        <select
                            className={'form-control'}
                            onChange={e => this.setState({layout: e.target.value})}
                            value={layout}
                        >
                            <option value="download">Download</option>
                            <option value="gallery">Gallery</option>
                            <option value="mapbox">Mapbox</option>
                        </select>
                    </div>
                </form>
            </div>
        }

        return <>
            {render(subData)}
        </>
    }
}

export default ThemeEditorProxy;

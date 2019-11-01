import React, {PureComponent} from 'react';
import {PropTypes} from 'prop-types';
import {dataShape} from "../props/dataShape";

class ThemeEditorProxy extends PureComponent {
    static propTypes = {
        data: dataShape.isRequired,
        render: PropTypes.func.isRequired,
    };

    state = {};

    static getDerivedStateFromProps(props, state) {
        if (!state.theme) {
            return {
                theme: props.data.theme,
                layout: props.data.layout,
            }
        }

        return null;
    }

    render() {
        const {theme, layout} = this.state;
        const {data, render} = this.props;

        const body = window.document.body;
        body.className = body.className.replace(/\btheme-.+\b/g, "");
        body.classList.add(`theme-${theme}`);

        return <>
            {render({
                ...data,
                layout,
                theme,
            })}
            <div
                className={'theme-editor'}
            >
                <h1>Display Editor</h1>
                <form>
                    <div className="form-group">
                        <label>Theme:</label>
                        <select
                            className={'form-control'}
                            onChange={e => this.setState({theme: e.target.value})}
                            value={theme}
                        >
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
                        </select>
                    </div>
                </form>
            </div>
        </>
    }
}

export default ThemeEditorProxy;

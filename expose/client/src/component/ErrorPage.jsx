import React from 'react'

export default function ErrorPage(props) {
    return (
        <div className={'error-page'}>
            <h1>{props.title}</h1>
            <h3>{props.code.toString()}</h3>
        </div>
    )
}

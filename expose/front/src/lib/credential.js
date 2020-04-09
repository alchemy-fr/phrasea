import Cookies from 'universal-cookie';
const cookieName = 'auth';

const cookies = new Cookies();

export function getAuthorization(publicationId) {
    const data = getData();

    return data[publicationId] || null;
}

export function setAuthorization(publicationId, auth) {
    const data = getData();

    data[publicationId] = auth;
    cookies.set(cookieName, JSON.stringify(data), {path: '/'});
}

function getData() {
    const cData = cookies.get(cookieName);
    return cData ? (typeof cData === 'string' ? JSON.parse(cData) : cData) : {};
}

class Store {
    set(key, value) {
        localStorage.setItem(key, value);
    }

    get(key) {
        return localStorage.getItem(key);
    }
}

const store = new Store();

export default store;

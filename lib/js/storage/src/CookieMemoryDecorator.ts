import Cookies from "js-cookie";

export const CookieMemoryDecorator: { cache: Record<string, string> } & Cookies.CookiesStatic = {
    attributes: Cookies.attributes,
    converter: Cookies.converter,

    cache: {},

    set(name: string, value: string, options: Cookies.CookieAttributes | undefined): string | undefined {
        CookieMemoryDecorator.cache[name] = value;
        Cookies.set(name, value, options);

        return undefined;
    },

    withAttributes(attributes: Cookies.CookieAttributes): Cookies.CookiesStatic<string> {
        return Cookies.withAttributes(attributes);
    },

    get(name?: string): any {
        if (!name) {
            return Cookies.get();
        }

        return CookieMemoryDecorator.cache[name] ?? Cookies.get(name);
    },

    remove(name: string, options?: Cookies.CookieAttributes): void {
        delete CookieMemoryDecorator.cache[name];

        Cookies.remove(name, options);
    },

    withConverter<TConv = string>(converter: Cookies.Converter<TConv>): Cookies.CookiesStatic<TConv> {
        return Cookies.withConverter(converter);
    }
}

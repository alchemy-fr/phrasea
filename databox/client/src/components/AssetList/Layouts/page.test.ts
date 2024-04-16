import {getPage} from "./page.ts";

test('getPage', () => {
    const pages: {id: string}[][] = [
        [
            {id: '1.1'},
            {id: '1.2'},
        ],
        [
            {id: '1.1'},
            {id: '1.2'},
            {id: '1.3'},
        ],
    ];

    expect(getPage(pages, 0)).toEqual({
        pageIndex: 0,
        itemIndex: 0,
        item: {id: '1.1'}
    });

    expect(getPage(pages, 1)).toEqual({
        pageIndex: 0,
        itemIndex: 1,
        item: {id: '1.2'}
    });

    expect(getPage(pages, 2)).toEqual({
        pageIndex: 1,
        itemIndex: 0,
        item: {id: '2.1'}
    });
    expect(getPage(pages, 3)).toEqual({
        pageIndex: 1,
        itemIndex: 1,
        item: {id: '2.2'}
    });
    expect(getPage(pages, 4)).toEqual({
        pageIndex: 1,
        itemIndex: 2,
        item: {id: '2.3'}
    });
});

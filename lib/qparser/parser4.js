/**
 * class Token
 */
class Token {
    constructor(val, tok, cla, typ, sub, end) {
        this._value = val;
        this._token = tok;
        this._class = cla;
        this._type  = typ;
        if (typeof sub != "undefined") {
            this._sub = sub;
        }
        if (typeof end != "undefined") {
            this._endWith = end;
        }
    }
}

class SyntaxError extends Error {
    constructor(msg, obj) {
        super(msg);
        console.error(msg, obj);
    }
}

/**
 * class Parser
 */
class _Parser {
    constructor() {
        String.prototype.isTerminal = function () {
            return "()<>!=\" \t\0:".indexOf(this) !== -1;
        }

        this._debug          = false;
        this._str            = null;
        this._p              = null;
        this._pmax           = null;
        this._tokenized      = null;
        this._tree           = null;
        this._selectionStart = null;
        this._selectionStart = null;

        this._languageTokens = [
            {
                '_token':    " ",
                '_class':    "space",
                '_type':     "Space",
                'RTerminal': true,
                'LTerminal': true,
                'okInQuote': true
            },
            {
                '_token':    "\t",
                '_class':    "space",
                '_type':     "Space",
                'RTerminal': true,
                'LTerminal': true,
                'okInQuote': true
            },
            {
                '_token':    "(",
                '_class':    "sub",
                '_type':     "OpenPar",
                'RTerminal': true,
                'LTerminal': true,
                '_endWith':  ')'
            },
            {
                '_token':    ")",
                '_class':    null,
                '_type':     "ClosePar",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                '_token':    "R\"",
                '_class':    "sub",
                '_type':     "QuoteRaw",
                'RTerminal': false,
                'LTerminal': true,
                '_endWith':  '"',
                'okInQuote': false
            },
            {
                '_token':    "\"",
                '_class':    "sub",
                '_type':     "Quote",
                'RTerminal': true,
                'LTerminal': true,
                '_endWith':  '"',
                'okInQuote': true
            },
            {
                '_token':    "EMPTY",
                '_class':    "keyword",
                '_type':     "Empty",
            },
            {
                '_token':    "IS NOT",
                '_class':    "compare",
                '_type':     "IsNot",
            },
            {
                '_token':    "IS",
                '_class':    "compare",
                '_type':     "Is",
            },
            {
                '_token':    "<=",
                '_class':    "compare",
                '_type':     "LtEq",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                '_token':    ">=",
                '_class':    "compare",
                '_type':     "GtEq",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                '_token':    "!=",
                '_class':    "compare",
                '_type':     "NotEq",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                '_token':    "<",
                '_class':    "compare",
                '_type':     "Lt",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                '_token':    ">",
                '_class':    "compare",
                '_type':     "Gt",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                '_token':    "=",
                '_class':    "compare",
                '_type':     "Eq",
                'RTerminal': true,
                'LTerminal': true
            },
            // { '_token': "!",      '_class': "unary",  '_type': "Not",         'RTerminal':true, 'LTerminal':true },
            // { '_token': "NOT",    '_class': "unary",  '_type': "Not",         'RTerminal':true, 'LTerminal':true },
            {
                '_token':    "\0",
                '_class':    null,
                '_type':     "Nul",
                'RTerminal': true,
                'LTerminal': true,
                '_endWith':  '\0'
            },
            {
                '_token': "AND",
                '_class': "binary",
                '_type':  "And"
            },
            {
                '_token':    "FIELD.",
                '_class':    "prefix",
                '_type':     "FieldPrefix",
                'RTerminal': false,
                'LTerminal': true
            },
            {
                '_token':    "META.",
                '_class':    "prefix",
                '_type':     "MetaPrefix",
                'RTerminal': false,
                'LTerminal': true
            },
            {
                '_token':    ":",
                '_class':    "compare",
                '_type':     "Column",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                '_token': "ET",
                '_class': "binary",
                '_type':  "And"
            },
            {
                '_token': "OR",
                '_class': "binary",
                '_type':  "Or"
            },
            {
                '_token': "OU",
                '_class': "binary",
                '_type':  "Or"
            },
            {
                '_token': "EXCEPT",
                '_class': "binary",
                '_type':  "Except"
            },
            {
                '_token': "SAUF",
                '_class': "binary",
                '_type':  "Except"
            },
            {
                '_token': "IN",
                '_class': "binary",
                '_type':  "In"
            },
            {
                '_token': "DANS",
                '_class': "binary",
                '_type':  "In"
            }
        ];

        this._specialTokens = {
            'concat' : {
                '_token': "+",
                '_class': "binary",
                '_type':  "Concat",
                'pstart': null,
                'pend': null,
            }
        };
    }

    setDebug(d) {
        this._debug = d;
        return this;
    }

    _log() {
        if (this._debug) {
            let args = [];
            for (let i = 0; i < arguments.length; i++) {
                if (typeof arguments[i] == "object") {
                    args.push(Object.assign({}, arguments[i]));
                }
                else {
                    args.push(arguments[i]);
                }
            }
            console.log.apply(this, args);
        }
    }

    _pushToken(tokens, token, pstart, pend) {
        if (typeof token == "string") {  // yes we can push a typeof string (buff)
            token = new Token(token, null, "word", "Word", null);
        }
        token.pstart = pstart - 1;    // -1 because we added a nul in the begining (and in the end)
        token.pend   = pend - 1;

        // wip/tryout : "spaces" are NOT pushed anymore

        // words and spaces go to the same "string" token
        let p;
        if (token._type === "Word" || token._type === "Space") {
            if (tokens.length === 0 || tokens[p = (tokens.length - 1)]._type !== "String") {
                // previous token is not "string", create a new one
                p = tokens.push(new Token("", null, "string", "String", [])) - 1;
            }
            // consecutives "space" tokens are merged
            if (token._type === "Space" && tokens[p]._sub.length > 0 && tokens[p]._sub[tokens[p]._sub.length - 1]._type === "Space") {
                tokens[p]._sub[tokens[p]._sub.length - 1]._value += token._value;
            }
            else {
                tokens[p]._sub.push(token);
            }
            // be nice, set main token ("string") value
            tokens[p]._value += (tokens[p]._value ? " " : "") + token._value;
        }
        else {
            // neither "word" or "space", just push
            tokens.push(token);
        }
    }

    /**
     * parse tokens while the ending token is found
     * the ending token matches the starting token for the current recursion.
     *
     * @param endWith       the token to stop parsing
     * @returns {[]}        a list of tokens
     * @private
     */
    _tokenize(endWith) {
        this._log("> enter recursion with " + this._str.substr(this._p) + "  (endWith " + endWith + ")");

        const tokens = [];    // returned

        let buff = "";  // the string buffer
        let buff_pstart = -1;    // the position of the first char, relative to this._str
        let buff_pend   = 0;    // the position of the last char, relative to this._str
        let again       = true;
        while (again && this._p < this._pmax) {

            this._log("str=\"" + this._str.substr(this._p) + "\" ; buff=\"" + buff + "\"");

            // quick test on some special one-char tokens...
            const c = this._str.charAt(this._p);
            // ... escape char
            if (c === '\\' && this._p + 1 < this._pmax) {
                if (buff_pstart === -1) {
                    buff_pstart = this._p;
                }
                buff += this._str.charAt(++this._p);
                buff_pend = this._p;
                this._p++;
                continue;
            }
            if(c === '\0') {
                // end of str : quit
                this._log("< quit recursion");
                again = false;
                continue;
            }

            let found = false;
            for (let i=0; !found && i < this._languageTokens.length; i++) {

                const t = this._languageTokens[i];
                const s = this._str.substr(this._p, t._token.length);

                if (s.toUpperCase() === t._token) {

                    this._log("\"" + this._str.substr(this._p) + "\" match token " + t._token);

                    const c_before = this._str.charAt(this._p - 1);
                    const c_after = this._str.charAt(this._p + t._token.length);

                    this._log("before:" + c_before + " after:" + c_after + " endWith:" + endWith);

                    if ((endWith !== '"' || t.okInQuote) && ((t.RTerminal || c_before.isTerminal()) && (t.LTerminal || c_after.isTerminal()))) {

                        // flush the "not a token"  buff
                        if (buff !== "") {
                            this._pushToken(tokens, buff, buff_pstart, buff_pend);  // yes we can push a typeof string (buff)
                            buff        = "";
                            buff_pstart = -1;
                        }

                        buff_pstart = this._p;
                        this._p += t._token.length;
                        buff_pend   = this._p - 1;

                        if (t._token === endWith) { // ... end of recursion char
                            this._log("< quit recursion");
                            // return from recursion
                            again = false;
                        }
                        else {
                            let sub = null;
                            if (typeof t._endWith != "undefined") {
                                // enter recursion
                                sub = this._tokenize(t._endWith);
                            }
                            if (t._class !== "space") {   // no need to push spaces
                                this._pushToken(tokens, new Token(
                                    s,
                                    t._token,
                                    t._class,
                                    t._type,
                                    sub,
                                    t._endWith
                                ), buff_pstart, buff_pend);
                            }
                            buff_pstart = -1;
                        }
                        found = true;
                    }
                }
            }
            if (!found) {
                this._log("add:" + c);
                if (buff_pstart === -1) {
                    buff_pstart = this._p;
                }
                buff += c;
                buff_pend = this._p;
                this._p++;
            }
        }

        // flush the last "not a token"  buff
        if (buff !== "") {
            this._pushToken(tokens, buff, buff_pstart, buff_pend);  // yes we can push a typeof string (buff)
        }

        this._log(tokens);

        return tokens;
    }

    /**
     * parse a string : build the list of tokens
     *
     * @returns {Parser}
     * @param q {string}                    // the full-text query
     * @param selectionStart {int}          // the position on the cursor (todo : will be used to help completion)
     * @param selectionEnd {int}
     */
    parse(q, selectionStart, selectionEnd) {
        this._str            = "\0" + q + "\0";
        this._p              = 1;
        this._pmax           = this._str.length + 1;      // +2 -1
        this._tokenized      = [];
        this._tree           = null;
        this._selectionStart = selectionStart;
        this._selectionEnd   = selectionEnd;

        if(this._debug) {
            console.clear();
        }

        let tokens = this._tokenize('\0');
        tokens     = this._fixPriority(tokens);

        this._tokenized = tokens;

        return this;
    }

    /**
     * private
     * fix priority of some tokens, by creating fake parenthesis (recursive sub list of tokens)
     *
     * @param tokens {[tokens]}
     * @returns {[]}
     * @private
     */
    _fixPriority(tokens) {
        const ret = [];

        for (let i = 0; i < tokens.length; i++) {
            const t = tokens[i];
            if (t._class === 'sub') {
                // recurse
                ret.push(new Token(
                    t._value,
                    t._token,
                    t._class,
                    t._type,
                    this._fixPriority(t._sub),
                    t._endWith
                ));
            }
            else {
                // meta.field=x --> ( meta. ( field = x ) )     // wrong !
                // meta.field=x --> ( ( meta. field ) = x )     // fixed !
                if (t._class === "prefix" && i < tokens.length - 1) {
                    // very high priority token, attached to next one (normally a field name)
                    let sub = [
                        t,
                        tokens[++i]
                    ];
                    // now push a "fake sub" token containing the prefix and field nama
                    ret.push(new Token(
                        '(',
                        '(',
                        'fake_sub',
                        'openPar',
                        sub,
                        ')'
                    ));
                    continue;
                }
                if (t._class === 'compare' && i > 0) {
                    // high priority token, add fake parenthesis (=fake_sub)
                    let sub = [
                        ret.pop(),  // the token before the "=" was already pushed
                        t           // the "="
                        // do not yet include the next token after "=", since it may not exist (str end with "=")
                    ];
                    // if there is a token after "=", include it
                    if (i < tokens.length - 1) {
                        sub.push(tokens[++i]);
                    }
                    // now push a "fake sub" token containing the expression
                    ret.push(new Token(
                        '(',
                        '(',
                        'fake_sub',
                        'openPar',
                        sub,
                        ')'
                    ));
                    continue;
                }

                // normal token
                ret.push(tokens[i]);
            }
        }

        return ret;
    };

    /**
     * returns the tokens list as html
     * for debug purpose only
     *
     * @returns {string}
     */
    dumpTokens() {
        const _dumpToken = function (t) {
            let s = "<span class=\"" + t._class + "\">";
            switch (t._class) {
                case "sub":
                case "fake_sub":
                    s += "<span class=\"" + t._class + "_char\">" + t._token + "</span>";
                    for (let i = 0; i < t._sub.length; i++) {
                        s += _dumpToken(t._sub[i]);
                    }
                    s += "<span class=\"" + t._class + "_char\">" + t._endWith + "</span>";
                    break;
                case "string":
                    for (let i = 0; i < t._sub.length; i++) {
                        s += _dumpToken(t._sub[i]);
                    }
                    break;
                case "word":
                    s += t._value;
                    break;
                case "space":
                    s += "&nbsp;";
                    break;
                default:
                    s += t._token;
                    break;
            }
            s += "</span>";
            return s;
        };

        let s = "";
        for (let i = 0; i < this._tokenized.length; i++) {
            s += _dumpToken(this._tokenized[i]);
        }

        return s;
    }

    /**
     * returns the ast as a html
     * for debug purpose only
     *
     * @returns {string}
     */
    dumpTree() {
        const _dumpPos = function (t) {
            return "<sub>" + (t.pstart) + "," + (t.pend) + "</sub>";
        };
        const _dumpTree = function (t) {
            if (t == null) {
                return "ø";
            }
            let s = "";
            switch (t.nodeType) {
                case "KEYWORD":
                    s += "<span class='" + t.token._class + "'>" + t.token._value + "</span>"
                        + _dumpPos(t.token);
                    break;
                case "STRING":
                    for (let i = 0; i < t.token._sub.length; i++) {
                        s += (s ? " " : "") + "<span class='" + t.token._sub[i]._class + "'>" + t.token._sub[i]._value + "</span>"
                            + _dumpPos(t.token._sub[i]);
                    }
                    break;
                case 'BINARY':
                case 'COMPARE':
                    s = "<table><tr><td colspan=\"2\"><span class='" + t.token._class + "'>" + t.token._type + "</span>"
                        + _dumpPos(t.token)
                        + "</td></tr>"
                        + "<tr><td>" + _dumpTree(t.l) + "</td><td>" + _dumpTree(t.r) + "</td></tr></table>";
                    break;
                case 'PREFIX':
                    s = "<table><tr><td style='border-bottom: none'><span class='" + t.prefix._class + "'>" + t.prefix._type + "</span>"
                        + _dumpPos(t.prefix)
                        + "</td></tr>"
                        + "<tr><td style='border-top: 1px dashed'>" + _dumpTree(t.r) + "</td></tr></table>";
                    break;
            }
            return s;
        };

        return _dumpTree(this._tree);
    }

    /**
     * returns the ast as json
     *
     * @returns {{tree: json, error: string}}
     */
    getTree() {

        const _addKeyword = (tree, token) => {
            if (tree == null) {
                return {
                    'nodeType': 'KEYWORD',
                    'token':    token
                };
            }
            if (tree.nodeType === 'BINARY' || tree.nodeType === 'COMPARE') {
                if (tree.r == null) {
                    tree.r = {
                        'nodeType': 'KEYWORD',
                        'token':    token
                    };
                    return tree;
                }
            }
            throw new SyntaxError("a keyword can't come after", tree);
        };

        const _addString = (tree, token) => {
            if (tree == null) {
                return {
                    'nodeType': 'STRING',
                    'token':    token
                };
            }
            if (tree.nodeType === 'BINARY' || tree.nodeType === 'COMPARE') {
                if (tree.r == null) {
                    tree.r = {
                        'nodeType': 'STRING',
                        'token':    token
                    };
                    return tree;
                }
            }
            // allow to add consecutives strings with a concat operator (so the string types are preserved)
            if(tree.nodeType === 'STRING') {
                let node = {
                    'nodeType': 'BINARY',
                    'token':   this._specialTokens.concat,
                    'l' : tree,
                    'r' : null
                };
                _addString(node, token); // recursive
                return node;
            }

            throw new SyntaxError("a string can't come after", tree);
        };

        const _addBinary = (tree, binaryToken) => {
            if (tree != null) {
                return {
                    'nodeType': 'BINARY',
                    'token':    binaryToken,
                    'l':        tree,
                    'r':        null
                };
            }

            throw new SyntaxError("A query can't start with an operator ", binaryToken);
        };

        const _addCompare = (tree, compareToken) => {
            if (tree != null) {
                return {
                    'nodeType': 'COMPARE',
                    'token':    compareToken,
                    'l':        tree,
                    'r':        null
                };
            }

            throw new SyntaxError("A query can't start with an operator ", compareToken);
        };

        const _addPrefix = (tree, prefixToken, suffixToken, depth) => {
            let node = {
                'nodeType': 'PREFIX',
                'prefix':   prefixToken,
                'r':        suffixToken ? _toTree([suffixToken], depth + 1) : null
            };
            if (tree == null) {
                return node;
            }
            if (tree.nodeType === 'BINARY') {
                if (tree.r == null) {
                    tree.r = node;
                    return tree;
                }
            }

            throw new SyntaxError("a prefix can't come after", tree);
        };

        const _addSub = (tree, subTree) => {
            if (tree == null) {
                return subTree;
            }
            if (tree.nodeType === 'BINARY' && tree.r == null) {
                tree.r = subTree;
                return tree;
            }
            // allow to add consecutives strings with a concat operator (so the string types are preserved)
            if(tree.nodeType === 'STRING' && (subTree === null || subTree.nodeType === 'STRING')) {
                let node = {
                    'nodeType': 'BINARY',
                    'token':   this._specialTokens.concat,
                    'l' : tree,
                    'r' : subTree
                };
                return node;
            }

            throw new SyntaxError("A subtree can't come after ", tree);
        };

        const _toTree = (tokens, depth) => {
            if (depth > 20) {
                return;
            }
            let tree = null;
            for (let i = 0; i < tokens.length; i++) {
                this._log(" ==", tokens);
                const t = tokens[i];
                if (this._selectionStart > t.pstart) {

                }
                this._log("  -", t);
                switch (t._class) {
                    case "string":
                        tree = _addString(tree, t);
                        break;
                    case "keyword":
                        tree = _addKeyword(tree, t);
                        break;
                    case "binary":
                        tree = _addBinary(tree, t);
                        break;
                    case "compare":
                        tree = _addCompare(tree, t);
                        break;
                    case "prefix":
                        let sfx = null;
                        if (i >= tokens.length - 1) {
                            throw new SyntaxError("something must follow the prefix ", t);
                        }
                        else {
                            sfx = tokens[++i];
                        }
                        tree = _addPrefix(tree, t, sfx, depth);
                        break;
                    case "sub":
                    case "fake_sub":
                        tree = _addSub(tree, _toTree(t._sub, depth + 1));
                        break;
                }
            }

            return tree;
        };

        this._log("=== ", this._tokenized);
        let errormsg = null;
        try {
            this._tree = _toTree(this._tokenized, 0);
        }
        catch (e) {
            this._tree = null;
            errormsg = e.message;
        }
        return {
            'error' : errormsg,
            'tree' : this._tree,
        }
    }

}


/**
 * no privates in js, so we export a facade which contains only public methods
 */
export class Parser {
    constructor() {
        // too bad : _p is anyway public, but it's always better than a bunch of methods
        this._p = new _Parser();
    }

    /**
     * activate verbose log in console (debug purpose only)
     * @param b {boolean}
     * @returns {Parser}
     */
    setDebug(b) {
        this._p.setDebug(b);
        return this;
    }

    /**
     * parse a string : build the list of tokens
     *
     * @param q {string}                    // the full-text query
     * @param selectionStart {int}          // the position on the cursor (todo : will be used to help completion)
     * @param selectionEnd {int}
     * @returns {Parser}
     */
    parse(q, selectionStart, selectionEnd) {
        return this._p.parse(q, selectionStart, selectionEnd);
    }

    /**
     * returns the ast as json
     *
     * @returns {{tree: json, error: string}}
     */
    getTree() {
        return this._p.getTree();
    }

    /**
     * returns the ast as a html
     * for debug purpose only
     *
     * @returns {string}
     */
    dumpTree() {
        return this._p.dumpTree();
    }

    /**
     * returns the tokens list as html
     * for debug purpose only
     *
     * @returns {string}
     */
    dumpTokens() {
        return this._p.dumpTokens();
    }
}
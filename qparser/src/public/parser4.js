/**
 * class Token
 */
class Token {
    constructor(text, match, cla, typ, sub, end) {
        this.text = text;
        this.match = match;
        this.class = cla;
        this.type  = typ;
        if (typeof sub != "undefined" && sub !== null) {
            this._sub = sub;
        }
        if (typeof end != "undefined") {
            this._endWith = end;
        }
    }

    toNode() {
        // for now, a "node" is the same as a "token" (easy)
        // but properties can be added after (l, r, ...)
        return Object.assign({}, this);
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
            return "()<>!=+-\" \t\0:".indexOf(this) !== -1;
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
                'match':    /^([0-9]+)\s*(days|day)/i,
                'class':    "DELAY",
                'type':     "Delay",
                'value':    null,
                'unit':     null,
                'tokenBuilder':  function(match) {
                    let t = new Token(
                        match[0],
                        this.match,
                        this.class,
                        this.type,
                    );
                    // add custom properties
                    t.value = parseInt(match[1]);
                    t.unit =  {'day':'DAY', 'days':'DAY'}[match[2].toLowerCase()];
                    return t;
                }
                // 'RTerminal': true,
                // 'LTerminal': true,
                // 'okInQuote': true
            },

            {
                'match':    " ",
                'class':    "SPACE",
                'type':     "Space",
                'RTerminal': true,
                'LTerminal': true,
                'okInQuote': true
            },
            {
                'match':    "\t",
                'class':    "SPACE",
                'type':     "Space",
                'RTerminal': true,
                'LTerminal': true,
                'okInQuote': true
            },
            {
                'match':    "(",
                'class':    "SUB",
                'type':     "OpenPar",
                'RTerminal': true,
                'LTerminal': true,
                '_endWith':  ')'
            },
            {
                'match':    ")",
                'class':    null,
                'type':     "ClosePar",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                'match':    "R\"",
                'class':    "SUB",
                'type':     "QuoteRaw",
                'RTerminal': false,
                'LTerminal': true,
                '_endWith':  '"',
                'okInQuote': false
            },
            {
                'match':    "\"",
                'class':    "SUB",
                'type':     "Quote",
                'RTerminal': true,
                'LTerminal': true,
                '_endWith':  '"',
                'okInQuote': true
            },
            {
                'match':    "EMPTY",
                'class':    "KEYWORD",
                'type':     "Empty",
            },
            {
                'match':    "IS NOT",
                'class':    "COMPARE",
                'type':     "IsNot",
            },
            {
                'match':    "IS",
                'class':    "COMPARE",
                'type':     "Is",
            },


            {
                'match':    "+",
                'class':    "ARITHMETIC",
                'type':     "PLUS",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                'match':    "-",
                'class':    "ARITHMETIC",
                'type':     "MINUS",
                'RTerminal': true,
                'LTerminal': true
            },


            {
                'match':    "<=",
                'class':    "COMPARE",
                'type':     "LtEq",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                'match':    ">=",
                'class':    "COMPARE",
                'type':     "GtEq",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                'match':    "!=",
                'class':    "COMPARE",
                'type':     "NotEq",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                'match':    "<",
                'class':    "COMPARE",
                'type':     "Lt",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                'match':    ">",
                'class':    "COMPARE",
                'type':     "Gt",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                'match':    "=",
                'class':    "COMPARE",
                'type':     "Eq",
                'RTerminal': true,
                'LTerminal': true
            },
            // { 'match': "!",      'class': "UNARY",  'type': "Not",         'RTerminal':true, 'LTerminal':true },
            // { 'match': "NOT",    'class': "UNARY",  'type': "Not",         'RTerminal':true, 'LTerminal':true },
            {
                'match':    "\0",
                'class':    null,
                'type':     "Nul",
                'RTerminal': true,
                'LTerminal': true,
                '_endWith':  '\0'
            },
            {
                'match': "AND",
                'class': "LOGICAL",
                'type':  "And"
            },
            {
                'match':    "FIELD.",
                'class':    "PREFIX",
                'type':     "FieldPrefix",
                'RTerminal': false,
                'LTerminal': true
            },
            {
                'match':    "META.",
                'class':    "PREFIX",
                'type':     "MetaPrefix",
                'RTerminal': false,
                'LTerminal': true
            },
            {
                'match':    ":",
                'class':    "COMPARE",
                'type':     "Column",
                'RTerminal': true,
                'LTerminal': true
            },
            {
                'match': "ET",
                'class': "LOGICAL",
                'type':  "And"
            },
            {
                'match': "OR",
                'class': "LOGICAL",
                'type':  "Or"
            },
            {
                'match': "OU",
                'class': "LOGICAL",
                'type':  "Or"
            },
            {
                'match': "EXCEPT",
                'class': "LOGICAL",
                'type':  "Except"
            },
            {
                'match': "SAUF",
                'class': "LOGICAL",
                'type':  "Except"
            },
            {
                'match': "IN",
                'class': "LOGICAL",
                'type':  "In"
            },
            {
                'match': "DANS",
                'class': "LOGICAL",
                'type':  "In"
            }
        ];

        this._specialNodes = {
            'concat' : {
                'class': "CONCAT",
                'type':  "Concat",
                'text': null,
                'position': {
                    'start': null,
                    'end':   null
                }
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
            token = new Token(token, null, "WORD", "Word", null);
        }
        token.position = {
            'start': pstart - 1,    // -1 because we added a nul in the begining (and in the end),
            'end': pend -1
        }

        // wip/tryout : "spaces" are NOT pushed anymore

        // words and spaces go to the same "string" token
        let p;
        if (token.type === "Word" || token.type === "Space") {
            if (tokens.length === 0 || tokens[p = (tokens.length - 1)].type !== "String") {
                // previous token is not "string", create a new one
                p = tokens.push(new Token("", null, "STRING", "String", [])) - 1;
            }
            // consecutives "SPACE" tokens are merged
            if (token.type === "Space" && tokens[p]._sub.length > 0 && tokens[p]._sub[tokens[p]._sub.length - 1].type === "Space") {
                tokens[p]._sub[tokens[p]._sub.length - 1].text += token.text;
            }
            else {
                tokens[p]._sub.push(token);
            }
            // be nice, set main token ("string") value
            tokens[p].text += (tokens[p].text ? " " : "") + token.text;
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

                let match = false;  // true if the current lng token (t) matches the query string at offset _p
                let text = "";      // the begining of query that matches
                let tokenBuilderArg = null;   // the argument to pass to the token builder if it exists (regexp tokens)

                if(t.match instanceof RegExp) {
                    // test using regexp
                    let  r = this._str.substr(this._p).match(t.match);
                    if(r !== null) {
                        match = true;
                        text = r[0];    // the full match
                        tokenBuilderArg = r;
                    }
                }
                else {
                    // test using simple string equality
                    text = this._str.substr(this._p, t.match.length);
                    match = (text.toUpperCase() === t.match);
                }

                if (match) {

                    this._log("\"" + this._str.substr(this._p) + "\" match token " + t.match.toString());

                    const c_before = this._str.charAt(this._p - 1);
                    const c_after = this._str.charAt(this._p + text.length);

                    this._log("before:" + c_before + " after:" + c_after + " endWith:" + endWith);

                    if ((endWith !== '"' || t.okInQuote) && ((t.RTerminal || c_before.isTerminal()) && (t.LTerminal || c_after.isTerminal()))) {

                        // flush the "not a token"  buff
                        if (buff !== "") {
                            this._pushToken(tokens, buff, buff_pstart, buff_pend);  // yes we can push a typeof string (buff)
                            buff        = "";
                            buff_pstart = -1;
                        }

                        buff_pstart = this._p;
                        this._p += text.length;
                        buff_pend   = this._p - 1;

                        if (text === endWith) { // ... end of recursion char
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
                            if (t.class !== "SPACE") {   // no need to push spaces
                                let token = null;
                                if(typeof t.tokenBuilder !== "undefined") {
                                    // this vocab (regexp) has his own builder
                                    token = t.tokenBuilder(tokenBuilderArg);
                                }
                                else {
                                    // simple vocab (string), simple
                                    token = new Token(
                                        text,
                                        t.match,
                                        t.class,
                                        t.type,
                                        sub,
                                        t._endWith
                                    );
                                }
                                this._pushToken(tokens, token, buff_pstart, buff_pend);
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
            if (t.class === 'SUB') {
                // recurse
                ret.push(new Token(
                    t.text,
                    t.match,
                    t.class,
                    t.type,
                    this._fixPriority(t._sub),
                    t._endWith
                ));
            }
            else {
                // meta.field=x --> ( meta. ( field = x ) )     // wrong !
                // meta.field=x --> ( ( meta. field ) = x )     // fixed !
                if (t.class === "PREFIX" && i < tokens.length - 1) {
                    // very high priority token, attached to next one (normally a field name)
                    let sub = [
                        t,
                        tokens[++i]
                    ];
                    // now push a "fake sub" token containing the prefix and field nama
                    ret.push(new Token(
                        '(',
                        '(',
                        'FAKE_SUB',
                        'openPar',
                        sub,
                        ')'
                    ));
                    continue;
                }
                if (t.class === 'COMPARE' && i > 0) {
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
                        'FAKE_SUB',
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
            let s = "<span class=\"" + t.class + "\">";
            switch (t.class) {
                case "SUB":
                case "FAKE_SUB":
                    s += "<span class=\"" + t.class + "_CHAR\">" + t.match + "</span>";
                    for (let i = 0; i < t._sub.length; i++) {
                        s += _dumpToken(t._sub[i]);
                    }
                    s += "<span class=\"" + t.class + "_CHAR\">" + t._endWith + "</span>";
                    break;
                case "STRING":
                    for (let i = 0; i < t._sub.length; i++) {
                        s += _dumpToken(t._sub[i]);
                    }
                    break;
                case "WORD":
                    s += t.text;
                    break;
                case "DELAY":
                    s += t.text;
                    break;
                case "SPACE":
                    s += "&nbsp;";
                    break;
                default:
                    s += t.match;
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
    dumpAST() {
        const _dumpPos = function (node) {
            return "<sub>" + (node.position.start) + "," + (node.position.end) + "</sub>";
        };
        const _dumpTree = function (node) {
            if (node == null) {
                return "ø";
            }
            let s = "";
            if(typeof(node.class) !== 'undefined') {
                switch (node.class) {
                    case "DELAY":
                        s += "<span class='" + node.class + "'>" + node.text + "</span>"
                            + _dumpPos(node);
                        break;
                    case "KEYWORD":
                        s += "<span class='" + node.class + "'>" + node.text + "</span>"
                            + _dumpPos(node);
                        break;
                    case "WORD":
                        s += "<span class='" + node.class + "'>" + node.text + "</span>"
                            + _dumpPos(node);
                        break;
                    case "STRING":
                        for (let i = 0; i < node._sub.length; i++) {
                            s += _dumpTree(node._sub[i]);
                        }
                        break;
                    case 'LOGICAL':
                    case 'COMPARE':
                    case 'CONCAT':
                    case 'ARITHMETIC':
                        s = "<table><tr><td colspan=\"2\"><span class='" + node.class + "'>" + node.type + "</span>"
                            + _dumpPos(node)
                            + "</td></tr>"
                            + "<tr><td>" + _dumpTree(node.l) + "</td><td>" + _dumpTree(node.r) + "</td></tr></table>";
                        break;
                    case 'PREFIX':
                        s = "<table><tr><td style='border-bottom: none'><span class='" + node.class + "'>" + node.type + "</span>"
                            + _dumpPos(node)
                            + "</td></tr>"
                            + "<tr><td style='border-top: 1px dashed'>" + _dumpTree(node.suffix) + "</td></tr></table>";
                        break;
                }
            }
            return s;
        };

        return _dumpTree(this._tree);
    }

    /**
     * returns the ast as json
     *
     * @returns {{ast: json, error: string}}
     */
    getAST() {

        const _addDelay = (tree, token) => {
            if (tree == null) {
                return token.toNode();
            }
            if (tree.class === 'ARITHMETIC' && tree.r == null) {
                tree.r = token.toNode();
                return tree;
            }

            throw new SyntaxError("a delay can't come after", tree);
        };

        const _addKeyword = (tree, token) => {
            if (tree == null) {
                return token.toNode();
            }
            // allow to add consecutives strings with a concat operator (so the string types are preserved)
            if(tree.class === 'STRING') {
                let n = Object.assign({}, this._specialNodes.concat);
                n.l = tree;
                n.r = null;
                _addKeyword(n, token); // recursive
                return n;
            }
            if (typeof(tree.r) != "undefined" && tree.r == null) {
                tree.r = token.toNode();
                return tree;
            }

            throw new SyntaxError("a keyword can't come after", tree);
        };

        const _addString = (tree, token) => {
            if (tree == null) {
                return token.toNode();
            }
            // allow to add consecutives strings with a concat operator (so the string types are preserved)
            if(tree.class === 'STRING') {
                let n = Object.assign({}, this._specialNodes.concat);
                n.l = tree;
                n.r = null;
                _addString(n, token); // recursive
                return n;
            }
            if (typeof(tree.r) != "undefined" && tree.r == null) {
                tree.r = token.toNode();
                return tree;
            }

            throw new SyntaxError("a string can't come after", tree);
        };

        const _addBinary = (tree, token) => {
            if (tree != null) {
                let n = token.toNode();
                n.l = tree;
                n.r = null;

                return n;
            }

            throw new SyntaxError("A query can't start with ", token);
        };

        const _addPrefix = (tree, prefixToken, suffixToken, depth) => {
            let node = Object.assign({}, prefixToken);
            node.suffix = suffixToken ? _toTree([suffixToken], depth + 1) : null;
            if (tree == null) {
                return node;
            }
            if (typeof(tree.r) != "undefined" && tree.r == null) {
                tree.r = node;
                return tree;
            }

            throw new SyntaxError("a prefix can't come after", tree);
        };

        const _addSub = (tree, subTree) => {
            if (tree == null) {
                return subTree;
            }
            // allow to add consecutives strings with a concat operator (so the string types are preserved)
            if(tree.class === 'STRING' && (subTree === null || subTree.class === 'STRING')) {
                let n =  Object.assign({}, this._specialNodes.concat);
                n.l = tree;
                n.r = subTree;
                return n;
            }
            if (typeof(tree.r) != "undefined" && tree.r == null) {
                tree.r = subTree;
                return tree;
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
                // --- wip code to handle cursor position
                // if (this._selectionStart > t.position.start) {
                //
                // }
                // ---
                this._log("  -", t);
                switch (t.class) {
                    case "STRING":
                        tree = _addString(tree, t);
                        break;
                    case "KEYWORD":
                        tree = _addKeyword(tree, t);
                        break;
                    case "DELAY":
                        tree = _addDelay(tree, t);
                        break;
                    case "LOGICAL":
                    case "COMPARE":
                    case "ARITHMETIC":
                        tree = _addBinary(tree, t);
                        break;
                    case "PREFIX":
                        let sfx = null;
                        if (i >= tokens.length - 1) {
                            throw new SyntaxError("something must follow the prefix ", t);
                        }
                        else {
                            sfx = tokens[++i];
                        }
                        tree = _addPrefix(tree, t, sfx, depth);
                        break;
                    case "SUB":
                    case "FAKE_SUB":
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
            'ast' : this._tree,
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
     * @returns {{ast: json, error: string}}
     */
    getAST() {
        return this._p.getAST();
    }

    /**
     * returns the ast as a html
     * for debug purpose only
     *
     * @returns {string}
     */
    dumpAST() {
        return this._p.dumpAST();
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
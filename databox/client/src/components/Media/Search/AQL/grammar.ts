// @ts-nocheck
// Generated automatically by nearley, version 2.20.1
// http://github.com/Hardmath123/nearley
// Bypasses TS6133. Allow declared but unused functions.
// @ts-ignore
function id(d: any[]): any { return d[0]; }

interface NearleyToken {
  value: any;
  [key: string]: any;
};

interface NearleyLexer {
  reset: (chunk: string, info: any) => void;
  next: () => NearleyToken | undefined;
  save: () => any;
  formatError: (token: never) => string;
  has: (tokenType: string) => boolean;
};

interface NearleyRule {
  name: string;
  symbols: NearleySymbol[];
  postprocess?: (d: any[], loc?: number, reject?: {}) => any;
};

type NearleySymbol = string | { literal: any } | { test: (token: any) => boolean };

interface Grammar {
  Lexer: NearleyLexer | undefined;
  ParserRules: NearleyRule[];
  ParserStart: string;
};

const grammar: Grammar = {
  Lexer: undefined,
  ParserRules: [
    {"name": "_$ebnf$1", "symbols": []},
    {"name": "_$ebnf$1", "symbols": ["_$ebnf$1", "wschar"], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "_", "symbols": ["_$ebnf$1"], "postprocess": function(d) {return null;}},
    {"name": "__$ebnf$1", "symbols": ["wschar"]},
    {"name": "__$ebnf$1", "symbols": ["__$ebnf$1", "wschar"], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "__", "symbols": ["__$ebnf$1"], "postprocess": function(d) {return null;}},
    {"name": "wschar", "symbols": [/[ \t\n\v\f]/], "postprocess": id},
    {"name": "unsigned_int$ebnf$1", "symbols": [/[0-9]/]},
    {"name": "unsigned_int$ebnf$1", "symbols": ["unsigned_int$ebnf$1", /[0-9]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "unsigned_int", "symbols": ["unsigned_int$ebnf$1"], "postprocess": 
        function(d) {
            return parseInt(d[0].join(""));
        }
        },
    {"name": "int$ebnf$1$subexpression$1", "symbols": [{"literal":"-"}]},
    {"name": "int$ebnf$1$subexpression$1", "symbols": [{"literal":"+"}]},
    {"name": "int$ebnf$1", "symbols": ["int$ebnf$1$subexpression$1"], "postprocess": id},
    {"name": "int$ebnf$1", "symbols": [], "postprocess": () => null},
    {"name": "int$ebnf$2", "symbols": [/[0-9]/]},
    {"name": "int$ebnf$2", "symbols": ["int$ebnf$2", /[0-9]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "int", "symbols": ["int$ebnf$1", "int$ebnf$2"], "postprocess": 
        function(d) {
            if (d[0]) {
                return parseInt(d[0][0]+d[1].join(""));
            } else {
                return parseInt(d[1].join(""));
            }
        }
        },
    {"name": "unsigned_decimal$ebnf$1", "symbols": [/[0-9]/]},
    {"name": "unsigned_decimal$ebnf$1", "symbols": ["unsigned_decimal$ebnf$1", /[0-9]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "unsigned_decimal$ebnf$2$subexpression$1$ebnf$1", "symbols": [/[0-9]/]},
    {"name": "unsigned_decimal$ebnf$2$subexpression$1$ebnf$1", "symbols": ["unsigned_decimal$ebnf$2$subexpression$1$ebnf$1", /[0-9]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "unsigned_decimal$ebnf$2$subexpression$1", "symbols": [{"literal":"."}, "unsigned_decimal$ebnf$2$subexpression$1$ebnf$1"]},
    {"name": "unsigned_decimal$ebnf$2", "symbols": ["unsigned_decimal$ebnf$2$subexpression$1"], "postprocess": id},
    {"name": "unsigned_decimal$ebnf$2", "symbols": [], "postprocess": () => null},
    {"name": "unsigned_decimal", "symbols": ["unsigned_decimal$ebnf$1", "unsigned_decimal$ebnf$2"], "postprocess": 
        function(d) {
            return parseFloat(
                d[0].join("") +
                (d[1] ? "."+d[1][1].join("") : "")
            );
        }
        },
    {"name": "decimal$ebnf$1", "symbols": [{"literal":"-"}], "postprocess": id},
    {"name": "decimal$ebnf$1", "symbols": [], "postprocess": () => null},
    {"name": "decimal$ebnf$2", "symbols": [/[0-9]/]},
    {"name": "decimal$ebnf$2", "symbols": ["decimal$ebnf$2", /[0-9]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "decimal$ebnf$3$subexpression$1$ebnf$1", "symbols": [/[0-9]/]},
    {"name": "decimal$ebnf$3$subexpression$1$ebnf$1", "symbols": ["decimal$ebnf$3$subexpression$1$ebnf$1", /[0-9]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "decimal$ebnf$3$subexpression$1", "symbols": [{"literal":"."}, "decimal$ebnf$3$subexpression$1$ebnf$1"]},
    {"name": "decimal$ebnf$3", "symbols": ["decimal$ebnf$3$subexpression$1"], "postprocess": id},
    {"name": "decimal$ebnf$3", "symbols": [], "postprocess": () => null},
    {"name": "decimal", "symbols": ["decimal$ebnf$1", "decimal$ebnf$2", "decimal$ebnf$3"], "postprocess": 
        function(d) {
            return parseFloat(
                (d[0] || "") +
                d[1].join("") +
                (d[2] ? "."+d[2][1].join("") : "")
            );
        }
        },
    {"name": "percentage", "symbols": ["decimal", {"literal":"%"}], "postprocess": 
        function(d) {
            return d[0]/100;
        }
        },
    {"name": "jsonfloat$ebnf$1", "symbols": [{"literal":"-"}], "postprocess": id},
    {"name": "jsonfloat$ebnf$1", "symbols": [], "postprocess": () => null},
    {"name": "jsonfloat$ebnf$2", "symbols": [/[0-9]/]},
    {"name": "jsonfloat$ebnf$2", "symbols": ["jsonfloat$ebnf$2", /[0-9]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "jsonfloat$ebnf$3$subexpression$1$ebnf$1", "symbols": [/[0-9]/]},
    {"name": "jsonfloat$ebnf$3$subexpression$1$ebnf$1", "symbols": ["jsonfloat$ebnf$3$subexpression$1$ebnf$1", /[0-9]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "jsonfloat$ebnf$3$subexpression$1", "symbols": [{"literal":"."}, "jsonfloat$ebnf$3$subexpression$1$ebnf$1"]},
    {"name": "jsonfloat$ebnf$3", "symbols": ["jsonfloat$ebnf$3$subexpression$1"], "postprocess": id},
    {"name": "jsonfloat$ebnf$3", "symbols": [], "postprocess": () => null},
    {"name": "jsonfloat$ebnf$4$subexpression$1$ebnf$1", "symbols": [/[+-]/], "postprocess": id},
    {"name": "jsonfloat$ebnf$4$subexpression$1$ebnf$1", "symbols": [], "postprocess": () => null},
    {"name": "jsonfloat$ebnf$4$subexpression$1$ebnf$2", "symbols": [/[0-9]/]},
    {"name": "jsonfloat$ebnf$4$subexpression$1$ebnf$2", "symbols": ["jsonfloat$ebnf$4$subexpression$1$ebnf$2", /[0-9]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "jsonfloat$ebnf$4$subexpression$1", "symbols": [/[eE]/, "jsonfloat$ebnf$4$subexpression$1$ebnf$1", "jsonfloat$ebnf$4$subexpression$1$ebnf$2"]},
    {"name": "jsonfloat$ebnf$4", "symbols": ["jsonfloat$ebnf$4$subexpression$1"], "postprocess": id},
    {"name": "jsonfloat$ebnf$4", "symbols": [], "postprocess": () => null},
    {"name": "jsonfloat", "symbols": ["jsonfloat$ebnf$1", "jsonfloat$ebnf$2", "jsonfloat$ebnf$3", "jsonfloat$ebnf$4"], "postprocess": 
        function(d) {
            return parseFloat(
                (d[0] || "") +
                d[1].join("") +
                (d[2] ? "."+d[2][1].join("") : "") +
                (d[3] ? "e" + (d[3][1] || "+") + d[3][2].join("") : "")
            );
        }
        },
    {"name": "main", "symbols": ["expression"], "postprocess": id},
    {"name": "expression$ebnf$1", "symbols": []},
    {"name": "expression$ebnf$1$subexpression$1$string$1", "symbols": [{"literal":"O"}, {"literal":"R"}], "postprocess": (d) => d.join('')},
    {"name": "expression$ebnf$1$subexpression$1", "symbols": ["__", "expression$ebnf$1$subexpression$1$string$1", "__", "and_condition"]},
    {"name": "expression$ebnf$1", "symbols": ["expression$ebnf$1", "expression$ebnf$1$subexpression$1"], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "expression", "symbols": ["and_condition", "expression$ebnf$1"], "postprocess": 
        function(data) {
            const conditions = [data[0]];
            data[1].forEach((d: any[]) => {
                conditions.push(d[3]);
            });
        
            if (conditions.length === 1) {
                return conditions[0];
            }
        
            return {
                operator: conditions.length > 1 ? "OR" : "AND",
                conditions
            };
        }
        },
    {"name": "and_condition$ebnf$1", "symbols": []},
    {"name": "and_condition$ebnf$1$subexpression$1$string$1", "symbols": [{"literal":"A"}, {"literal":"N"}, {"literal":"D"}], "postprocess": (d) => d.join('')},
    {"name": "and_condition$ebnf$1$subexpression$1", "symbols": ["__", "and_condition$ebnf$1$subexpression$1$string$1", "__", "condition"]},
    {"name": "and_condition$ebnf$1", "symbols": ["and_condition$ebnf$1", "and_condition$ebnf$1$subexpression$1"], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "and_condition", "symbols": ["condition", "and_condition$ebnf$1"], "postprocess": 
        function(data) {
            const conditions = [data[0]];
        
            data[1].forEach((d: any[]) => {
                conditions.push(d[3]);
            });
        
            if (conditions.length === 1) {
                return conditions[0];
            }
        
            return {
                operator: "AND",
                conditions,
            };
        }
        },
    {"name": "condition$subexpression$1", "symbols": [{"literal":"("}, "expression", {"literal":")"}]},
    {"name": "condition", "symbols": ["condition$subexpression$1"], "postprocess": (data) => ({operator: "AND", conditions: [data[1]]})},
    {"name": "condition$string$1", "symbols": [{"literal":"N"}, {"literal":"O"}, {"literal":"T"}], "postprocess": (d) => d.join('')},
    {"name": "condition", "symbols": ["condition$string$1", "__", "expression"], "postprocess": (data) => ({operator: "NOT", conditions: [data[3]]})},
    {"name": "condition", "symbols": ["criteria"], "postprocess": id},
    {"name": "criteria", "symbols": ["field", "_", "operator"], "postprocess": 
        function(data) {
            return {
                leftOperand: data[0],
                ...data[2]
            };
        }
        },
    {"name": "builtin_field$ebnf$1", "symbols": [/[a-zA-Z0-9_]/]},
    {"name": "builtin_field$ebnf$1", "symbols": ["builtin_field$ebnf$1", /[a-zA-Z0-9_]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "builtin_field", "symbols": [{"literal":"@"}, "builtin_field$ebnf$1"], "postprocess": d => ({field: "@"+d[1].join('')})},
    {"name": "field_name$ebnf$1", "symbols": []},
    {"name": "field_name$ebnf$1", "symbols": ["field_name$ebnf$1", /[a-zA-Z0-9_-]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "field_name", "symbols": [/[a-zA-Z_]/, "field_name$ebnf$1"], "postprocess": d => ({field: d[0]+d[1].join('')})},
    {"name": "field", "symbols": ["builtin_field"], "postprocess": id},
    {"name": "field", "symbols": ["field_name"], "postprocess": id},
    {"name": "boolean$string$1", "symbols": [{"literal":"t"}, {"literal":"r"}, {"literal":"u"}, {"literal":"e"}], "postprocess": (d) => d.join('')},
    {"name": "boolean", "symbols": ["boolean$string$1"], "postprocess": () => true},
    {"name": "boolean$string$2", "symbols": [{"literal":"f"}, {"literal":"a"}, {"literal":"l"}, {"literal":"s"}, {"literal":"e"}], "postprocess": (d) => d.join('')},
    {"name": "boolean", "symbols": ["boolean$string$2"], "postprocess": () => false},
    {"name": "operator$ebnf$1$subexpression$1$string$1", "symbols": [{"literal":"N"}, {"literal":"O"}, {"literal":"T"}], "postprocess": (d) => d.join('')},
    {"name": "operator$ebnf$1$subexpression$1", "symbols": ["operator$ebnf$1$subexpression$1$string$1", "__"]},
    {"name": "operator$ebnf$1", "symbols": ["operator$ebnf$1$subexpression$1"], "postprocess": id},
    {"name": "operator$ebnf$1", "symbols": [], "postprocess": () => null},
    {"name": "operator$string$1", "symbols": [{"literal":"B"}, {"literal":"E"}, {"literal":"T"}, {"literal":"W"}, {"literal":"E"}, {"literal":"E"}, {"literal":"N"}], "postprocess": (d) => d.join('')},
    {"name": "operator$string$2", "symbols": [{"literal":"A"}, {"literal":"N"}, {"literal":"D"}], "postprocess": (d) => d.join('')},
    {"name": "operator", "symbols": ["__", "operator$ebnf$1", "operator$string$1", "__", "number", "__", "operator$string$2", "__", "number"], "postprocess": (data) => ({operator: data[1] ? 'NOT_BETWEEN' : 'BETWEEN', rightOperand: [data[4], data[8]]})},
    {"name": "operator$string$3", "symbols": [{"literal":"I"}, {"literal":"S"}], "postprocess": (d) => d.join('')},
    {"name": "operator$string$4", "symbols": [{"literal":"M"}, {"literal":"I"}, {"literal":"S"}, {"literal":"S"}, {"literal":"I"}, {"literal":"N"}, {"literal":"G"}], "postprocess": (d) => d.join('')},
    {"name": "operator", "symbols": ["__", "operator$string$3", "__", "operator$string$4"], "postprocess": () => ({operator: 'MISSING'})},
    {"name": "operator$string$5", "symbols": [{"literal":"E"}, {"literal":"X"}, {"literal":"I"}, {"literal":"S"}, {"literal":"T"}, {"literal":"S"}], "postprocess": (d) => d.join('')},
    {"name": "operator", "symbols": ["__", "operator$string$5"], "postprocess": () => ({operator: 'EXISTS'})},
    {"name": "operator", "symbols": ["simple_operator", "_", "value"], "postprocess": (data) => ({operator: data[0], rightOperand: data[2]})},
    {"name": "operator", "symbols": ["in_operator"], "postprocess": id},
    {"name": "in_operator$ebnf$1$subexpression$1$string$1", "symbols": [{"literal":"N"}, {"literal":"O"}, {"literal":"T"}], "postprocess": (d) => d.join('')},
    {"name": "in_operator$ebnf$1$subexpression$1", "symbols": ["in_operator$ebnf$1$subexpression$1$string$1", "__"]},
    {"name": "in_operator$ebnf$1", "symbols": ["in_operator$ebnf$1$subexpression$1"], "postprocess": id},
    {"name": "in_operator$ebnf$1", "symbols": [], "postprocess": () => null},
    {"name": "in_operator$string$1", "symbols": [{"literal":"I"}, {"literal":"N"}], "postprocess": (d) => d.join('')},
    {"name": "in_operator$ebnf$2", "symbols": []},
    {"name": "in_operator$ebnf$2$subexpression$1", "symbols": ["_", {"literal":","}, "_", "value"]},
    {"name": "in_operator$ebnf$2", "symbols": ["in_operator$ebnf$2", "in_operator$ebnf$2$subexpression$1"], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "in_operator", "symbols": ["__", "in_operator$ebnf$1", "in_operator$string$1", "_", {"literal":"("}, "_", "value", "in_operator$ebnf$2", "_", {"literal":")"}], "postprocess": (data) => {
           return {
               operator: data[1] ? 'NOT_IN' : 'IN',
               rightOperand: [data[6]].concat(data[7].map(d => d[3])),
           };
        } },
    {"name": "simple_operator", "symbols": [{"literal":"="}], "postprocess": id},
    {"name": "simple_operator$string$1", "symbols": [{"literal":"!"}, {"literal":"="}], "postprocess": (d) => d.join('')},
    {"name": "simple_operator", "symbols": ["simple_operator$string$1"], "postprocess": id},
    {"name": "simple_operator", "symbols": [{"literal":">"}], "postprocess": id},
    {"name": "simple_operator", "symbols": [{"literal":"<"}], "postprocess": id},
    {"name": "simple_operator$string$2", "symbols": [{"literal":">"}, {"literal":"="}], "postprocess": (d) => d.join('')},
    {"name": "simple_operator", "symbols": ["simple_operator$string$2"], "postprocess": id},
    {"name": "simple_operator$string$3", "symbols": [{"literal":"<"}, {"literal":"="}], "postprocess": (d) => d.join('')},
    {"name": "simple_operator", "symbols": ["simple_operator$string$3"], "postprocess": id},
    {"name": "simple_operator$string$4", "symbols": [{"literal":"C"}, {"literal":"O"}, {"literal":"N"}, {"literal":"T"}, {"literal":"A"}, {"literal":"I"}, {"literal":"N"}, {"literal":"S"}], "postprocess": (d) => d.join('')},
    {"name": "simple_operator", "symbols": ["__", "simple_operator$string$4"], "postprocess": id},
    {"name": "simple_operator$string$5", "symbols": [{"literal":"M"}, {"literal":"A"}, {"literal":"T"}, {"literal":"C"}, {"literal":"H"}, {"literal":"E"}, {"literal":"S"}], "postprocess": (d) => d.join('')},
    {"name": "simple_operator", "symbols": ["__", "simple_operator$string$5"], "postprocess": id},
    {"name": "simple_operator$string$6", "symbols": [{"literal":"S"}, {"literal":"T"}, {"literal":"A"}, {"literal":"R"}, {"literal":"T"}, {"literal":"S"}, {"literal":"_"}, {"literal":"W"}, {"literal":"I"}, {"literal":"T"}, {"literal":"H"}], "postprocess": (d) => d.join('')},
    {"name": "simple_operator", "symbols": ["__", "simple_operator$string$6"], "postprocess": id},
    {"name": "number", "symbols": ["int"], "postprocess": id},
    {"name": "number", "symbols": ["decimal"], "postprocess": id},
    {"name": "value", "symbols": ["number"], "postprocess": id},
    {"name": "value", "symbols": ["quoted_string"], "postprocess": id},
    {"name": "value", "symbols": ["boolean"], "postprocess": id},
    {"name": "quoted_string$ebnf$1", "symbols": []},
    {"name": "quoted_string$ebnf$1", "symbols": ["quoted_string$ebnf$1", /[^"]/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "quoted_string", "symbols": [{"literal":"\""}, "quoted_string$ebnf$1", {"literal":"\""}], "postprocess": d => ({literal: d[1].join('')})},
    {"name": "quoted_string$ebnf$2", "symbols": []},
    {"name": "quoted_string$ebnf$2", "symbols": ["quoted_string$ebnf$2", /[^']/], "postprocess": (d) => d[0].concat([d[1]])},
    {"name": "quoted_string", "symbols": [{"literal":"'"}, "quoted_string$ebnf$2", {"literal":"'"}], "postprocess": d => ({literal: d[1].join('')})}
  ],
  ParserStart: "main",
};

export default grammar;

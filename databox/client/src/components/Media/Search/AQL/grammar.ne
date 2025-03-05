@builtin "whitespace.ne"
@builtin "number.ne"

main -> expression {% id %}

expression -> and_condition (__ "OR" __ and_condition):* {%
    function(data) {
        const conditions = [data[0]];
        data[1].forEach((d) => {
            conditions.push(d[3]);
        });

        return {
            operator: conditions.length > 1 ? "OR" : "AND",
            conditions: [data[0]]
        };
    }
%}

and_condition -> condition (__ "AND" __ condition):* {%
    function(data) {
        const conditions = [data[0]];

        data[1].forEach((d) => {
            conditions.push(d[3]);
        });

        return {
            operator: "AND",
            conditions,
        };
    }
%}

condition -> ("(" expression ")") {% (data) => ({operator: "AND", conditions: [data[1]]}) %}
    | "NOT" __ expression {% (data) => ({operator: "NOT", conditions: [data[3]]}) %}
    | criteria {% id %}

quoted_string -> "\"" [^"]:* "\"" {% d => ({literal: d[1].join('')}) %}
    | "'" [^']:* "'" {% d => ({literal: d[1].join('')}) %}

builtin_field -> "@" [a-zA-Z0-9_]:+ {% d => ({field: "@"+d[1].join('')}) %}

field_name -> [a-zA-Z_] [a-zA-Z0-9_-]:* {% d => ({field: d[0]+d[1].join('')}) %}

field -> builtin_field {% id %}
    | field_name {% id %}

boolean -> "true" {% d => true %}
    | "false" {% d => false %}

criteria -> field _ operator _ value {%
    function(data) {
                console.log('data', data);
        return {
            operator: data[2],
            leftOperand:  data[0],
            rightOperand: data[4],
        };
    }
%}

operator -> "=" {% id %}
    | "!=" {% id %}
    | ">" {% id %}
    | "<" {% id %}
    | ">=" {% id %}
    | "<=" {% id %}
    | "contains" {% id %}
    | "in" {% id %}
    | "not in" {% id %}

number -> int {% id %}
    | decimal {% id %}

value -> number {% id %}
    | quoted_string {% id %}
    | boolean {% id %}

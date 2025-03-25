@preprocessor typescript
@builtin "whitespace.ne"
@builtin "number.ne"

main -> expression {% id %}

expression -> and_condition (__ "OR" __ and_condition):* {%
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
%}

and_condition -> condition (__ "AND" __ condition):* {%
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
%}

condition -> "(" expression ")" {% (data) => data[1] %}
    | "NOT" __ expression {% (data) => ({operator: "NOT", conditions: [data[3]]}) %}
    | criteria {% id %}

criteria -> field _ operator {%
    function(data) {
        return {
            leftOperand: data[0],
            ...data[2]
        };
    }
%}

builtin_field -> "@" [a-zA-Z0-9_]:+ {% d => ({field: "@"+d[1].join('')}) %}

field_name -> [a-zA-Z_] [a-zA-Z0-9_-]:* {% d => ({field: d[0]+d[1].join('')}) %}

field_or_value -> field {% id %}
    | value {% id %}

field -> builtin_field {% id %}
    | field_name {% id %}

boolean -> "true" {% () => true %}
    | "false" {% () => false %}

operator -> __ ("NOT" __):? "BETWEEN" __ number __ "AND" __ number {% (data) => ({operator: data[1] ? 'NOT_BETWEEN' : 'BETWEEN', rightOperand: [data[4], data[8]]}) %}
    | __ "IS" __ "MISSING" {% () => ({operator: 'MISSING'}) %}
    | __ "EXISTS" {% () => ({operator: 'EXISTS'}) %}
    | simple_operator _ field_or_value {% (data) => ({operator: data[0], rightOperand: data[2]}) %}
    | in_operator {% id %}

in_operator -> __ ("NOT" __):? "IN" _ "(" _ value (_ "," _ value):* _ ")" {% (data) => {
    return {
        operator: data[1] ? 'NOT_IN' : 'IN',
        rightOperand: [data[6]].concat(data[7].map(d => d[3])),
    };
 } %}

simple_operator -> "=" {% id %}
    | "!=" {% id %}
    | ">" {% id %}
    | "<" {% id %}
    | ">=" {% id %}
    | "<=" {% id %}
    | __ "CONTAINS" {% d => d[1] %}
    | __ "MATCHES" {% d => d[1] %}
    | __ "STARTS" __ "WITH" {% () => 'STARTS_WITH' %}

number -> int {% id %}
    | decimal {% id %}

value -> number {% id %}
    | quoted_string {% id %}
    | boolean {% id %}

quoted_string -> "\"" (escape_double | [^"]):* "\"" {% d => ({literal: d[1].join('')}) %}
    | "'" (escape_single | [^']):* "'" {% d => ({literal: d[1].join('')}) %}

escape_double -> "\\" ["] {% () => '"' %}
    | escape_backslash {% id %}

escape_single -> "\\" ["] {% () => '"' %}
    | escape_backslash {% id %}

escape_backslash -> "\\" "\\" {% () => '\\' %}

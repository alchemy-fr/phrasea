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

condition -> ("(" expression ")") {% (data) => ({operator: "AND", conditions: [data[1]]}) %}
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

field -> builtin_field {% id %}
    | field_name {% id %}

boolean -> "true" {% () => true %}
    | "false" {% () => false %}

operator -> __ "BETWEEN" __ number __ "AND" __ number {% (data) => ({operator: 'BETWEEN', rightOperand: [data[2], data[6]]}) %}
    | __ "IS" __ "MISSING" {% () => ({operator: 'MISSING'}) %}
    | __ "EXISTS" {% () => ({operator: 'EXISTS'}) %}
    | simple_operator _ value {% (data) => ({operator: data[0], rightOperand: data[2]}) %}

simple_operator -> "=" {% id %}
    | "!=" {% id %}
    | ">" {% id %}
    | "<" {% id %}
    | ">=" {% id %}
    | "<=" {% id %}
    | __ "contains" {% id %}
    | __ "in" {% id %}
    | __ "not in" {% id %}

number -> int {% id %}
    | decimal {% id %}

value -> number {% id %}
    | quoted_string {% id %}
    | boolean {% id %}

quoted_string -> "\"" [^"]:* "\"" {% d => ({literal: d[1].join('')}) %}
    | "'" [^']:* "'" {% d => ({literal: d[1].join('')}) %}

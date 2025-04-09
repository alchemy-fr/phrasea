@preprocessor typescript
@builtin "whitespace.ne"
@builtin "number.ne"

main -> expression {% id %}

expression -> and_expression (__ "OR" __ and_expression):* {%
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

and_expression -> condition (__ "AND" __ condition):* {%
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

operator -> __ ("NOT" __):? "BETWEEN" __ value_expression __ "AND" __ value_expression {% (data) => ({operator: data[1] ? 'NOT_BETWEEN' : 'BETWEEN', rightOperand: [data[4], data[8]]}) %}
    | __ "IS" __ "MISSING" {% () => ({operator: 'MISSING'}) %}
    | __ "EXISTS" {% () => ({operator: 'EXISTS'}) %}
    | in_operator {% id %}
    | geo_operator {% id %}
    | simple_operator _ value_expression {% (data) => ({operator: data[0], rightOperand: data[2]}) %}


geo_operator -> "WITHIN" __ (within_circle_operator | within_rectangle_operator) {% (data) => {
    return data[2][0];
} %}

within_circle_operator -> "CIRCLE" _ "(" _ value_expression _ "," _ value_expression _ "," _ value_expression _ ")" {% (data) => {
    return {
        operator: 'WITHIN_CIRCLE',
        rightOperand: [
            data[4],
            data[8],
            data[12],
        ],
    };
} %}

within_rectangle_operator -> "RECTANGLE" _ "(" _ value_expression _ "," _ value_expression _ "," _ value_expression _ "," _ value_expression _ ")" {% (data) => {
    return {
        operator: 'WITHIN_RECTANGLE',
        rightOperand: [
            data[4],
            data[8],
            data[12],
            data[16],
        ],
    };
} %}

in_operator -> __ ("NOT" __):? "IN" _ "(" _ value_expression (_ "," _ value_expression):* _ ")" {% (data) => {
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
    | "DOES" __ "NOT" __ "CONTAIN" {% () => 'NOT_CONTAINS' %}
    | __ "MATCHES" {% d => d[1] %}
    | "DOES" __ "NOT" __ "MATCH" {% () => 'NOT_MATCHES' %}
    | __ "STARTS" __ "WITH" {% () => 'STARTS_WITH' %}
    | "DOES" __ "NOT" __ "START" __ "WITH" {% () => 'NOT_STARTS_WITH' %}

function_call -> identifier "(" _ value_expression:? (_ "," _ value_expression):* _ ")" {% (data) => {
    const args = [];
    if (data[3]) {
        args.push(data[3]);
    }
    data[4].forEach((d) => {
        args.push(d[3]);
    });

    return {
        type: 'function_call',
        function: data[0],
        arguments: args,
    };
} %}

value_expression -> value_sum {% id %}

value_product -> value_or_expr (_ ("/" | "*") _ value_or_expr):* {% (data) => {
    function handleOperator(l, r, operator) {
        return {
            type: 'value_expression',
            operator,
            leftOperand: l,
            rightOperand: r,
        };
    }

    let result = data[0];

    data[1].forEach(([, operator, , rightOperand]) => {
        result = handleOperator(result, rightOperand, operator[0]);
    });

    return result;
} %}

value_sum -> value_product ( _ ("+" | "-") _ value_product):* {% (data) => {
    function handleOperator(l, r, operator) {
        return {
            type: 'value_expression',
            operator,
            leftOperand: l,
            rightOperand: r
        };
    }

    let result = data[0];

    data[1].forEach(([, operator, , rightOperand]) => {
        result = handleOperator(result, rightOperand, operator[0]);
    });

    return result;
} %}

value_or_expr -> value {% id %}
    | "(" _ value_expression _ ")" {% (data) => ({
        type: 'parentheses',
        expression: data[2],
    }) %}

number -> int {% id %}
    | decimal {% id %}

value -> function_call {% id %}
    | number {% id %}
    | quoted_string {% id %}
    | boolean {% id %}
    | field {% id %}

boolean -> "true" {% () => true %}
    | "false" {% () => false %}

quoted_string -> "\"" (escape_double | [^"]):* "\"" {% d => ({literal: d[1].join('')}) %}
    | "'" (escape_single | [^']):* "'" {% d => ({literal: d[1].join('')}) %}

identifier -> [a-zA-Z_] [a-zA-Z0-9_-]:* {% d => d[0]+d[1].join('') %}

builtin_field -> "@" identifier {% d => ({field: "@"+d[1]}) %}

field -> builtin_field {% id %}
    | identifier {% d => ({field: d[0]}) %}

escape_double -> "\\" ["] {% () => '"' %}
    | escape_backslash {% id %}

escape_single -> "\\" ["] {% () => '"' %}
    | escape_backslash {% id %}

escape_backslash -> "\\" "\\" {% () => '\\' %}

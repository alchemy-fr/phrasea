<?php

namespace App\Elasticsearch\AQL;

use hafriedlander\Peg\Parser;

class AQLGrammar extends Parser\Basic
{
/* main: e:expression */
protected $match_main_typestack = ['main'];
function match_main($stack = []) {
	$matchrule = 'main';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'expression'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres, "e");
		return $this->finalise($result);
	}
	else { return \false; }
}

public function main__finalise (&$result) {
        $result['data'] = $result['e']['data'];
        unset($result['e']);
    }

/* expression: left:and_expression (] "OR" ] right:and_expression ) * */
protected $match_expression_typestack = ['expression'];
function match_expression($stack = []) {
	$matchrule = 'expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13043 = \null;
	do {
		$key = 'match_'.'and_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_13043 = \false; break; }
		while (\true) {
			$res_13042 = $result;
			$pos_13042 = $this->pos;
			$_13041 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_13041 = \false; break; }
				if (($subres = $this->literal('OR')) !== \false) { $result["text"] .= $subres; }
				else { $_13041 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_13041 = \false; break; }
				$key = 'match_'.'and_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_13041 = \false; break; }
				$_13041 = \true; break;
			}
			while(\false);
			if($_13041 === \false) {
				$result = $res_13042;
				$this->setPos($pos_13042);
				unset($res_13042, $pos_13042);
				break;
			}
		}
		$_13043 = \true; break;
	}
	while(\false);
	if($_13043 === \true) { return $this->finalise($result); }
	if($_13043 === \false) { return \false; }
}

public function expression__finalise (&$result) {
        $result['operator'] = 'OR';
        $conditions = [$result['left']['data']];
        if (isset($result['right']['_matchrule'])) {
            $conditions[] = $result['right']['data'];
        } else {
            foreach ($result['right'] ?? [] as $right) {
                $conditions[] = $right['data'];
            }
        }
        unset($result['left'], $result['right']);
        if (count($conditions) === 1) {
            $result['data'] = $conditions[0];
            return;
        }
        $result['data'] = [
            'type' => 'expression',
            'operator' => 'OR',
            'conditions' => $conditions,
        ];
    }

/* and_expression: left:condition (] "AND" ] right:condition ) * */
protected $match_and_expression_typestack = ['and_expression'];
function match_and_expression($stack = []) {
	$matchrule = 'and_expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13052 = \null;
	do {
		$key = 'match_'.'condition'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_13052 = \false; break; }
		while (\true) {
			$res_13051 = $result;
			$pos_13051 = $this->pos;
			$_13050 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_13050 = \false; break; }
				if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
				else { $_13050 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_13050 = \false; break; }
				$key = 'match_'.'condition'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_13050 = \false; break; }
				$_13050 = \true; break;
			}
			while(\false);
			if($_13050 === \false) {
				$result = $res_13051;
				$this->setPos($pos_13051);
				unset($res_13051, $pos_13051);
				break;
			}
		}
		$_13052 = \true; break;
	}
	while(\false);
	if($_13052 === \true) { return $this->finalise($result); }
	if($_13052 === \false) { return \false; }
}

public function and_expression__finalise (&$result) {
        $conditions = [$result['left']['data']];
        if (isset($result['right']['_matchrule'])) {
            $conditions[] = $result['right']['data'];
        } else {
            foreach ($result['right'] ?? [] as $right) {
                $conditions[] = $right['data'];
            }
        }
        unset($result['left'], $result['right']);
        if (count($conditions) === 1) {
            $result['data'] = $conditions[0];
            return;
        }
        $result['data'] = [
            'type' => 'expression',
            'operator' => 'AND',
            'conditions' => $conditions,
        ];
    }

/* condition: "(" > e:expression > ")"
    | e:not_expression
    | e:criteria */
protected $match_condition_typestack = ['condition'];
function match_condition($stack = []) {
	$matchrule = 'condition';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13067 = \null;
	do {
		$res_13054 = $result;
		$pos_13054 = $this->pos;
		$_13060 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_13060 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
			}
			else { $_13060 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_13060 = \false; break; }
			$_13060 = \true; break;
		}
		while(\false);
		if($_13060 === \true) { $_13067 = \true; break; }
		$result = $res_13054;
		$this->setPos($pos_13054);
		$_13065 = \null;
		do {
			$res_13062 = $result;
			$pos_13062 = $this->pos;
			$key = 'match_'.'not_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_13065 = \true; break;
			}
			$result = $res_13062;
			$this->setPos($pos_13062);
			$key = 'match_'.'criteria'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_13065 = \true; break;
			}
			$result = $res_13062;
			$this->setPos($pos_13062);
			$_13065 = \false; break;
		}
		while(\false);
		if($_13065 === \true) { $_13067 = \true; break; }
		$result = $res_13054;
		$this->setPos($pos_13054);
		$_13067 = \false; break;
	}
	while(\false);
	if($_13067 === \true) { return $this->finalise($result); }
	if($_13067 === \false) { return \false; }
}

public function condition__finalise (&$result) {
        $result['data'] = $result['e']['data'];
        unset($result['e']);
    }

/* not_expression: "NOT" __ e:expression */
protected $match_not_expression_typestack = ['not_expression'];
function match_not_expression($stack = []) {
	$matchrule = 'not_expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13072 = \null;
	do {
		if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
		else { $_13072 = \false; break; }
		$key = 'match_'.'__'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_13072 = \false; break; }
		$key = 'match_'.'expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "e"); }
		else { $_13072 = \false; break; }
		$_13072 = \true; break;
	}
	while(\false);
	if($_13072 === \true) { return $this->finalise($result); }
	if($_13072 === \false) { return \false; }
}

public function not_expression__finalise (&$result) {
        $result['data'] = [
            'type' => 'expression',
            'operator' => 'NOT',
            'conditions' => [$result['e']['data']],
        ];
        unset($result['e']);
    }

/* criteria: field:field op:operator */
protected $match_criteria_typestack = ['criteria'];
function match_criteria($stack = []) {
	$matchrule = 'criteria';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13076 = \null;
	do {
		$key = 'match_'.'field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "field");
		}
		else { $_13076 = \false; break; }
		$key = 'match_'.'operator'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_13076 = \false; break; }
		$_13076 = \true; break;
	}
	while(\false);
	if($_13076 === \true) { return $this->finalise($result); }
	if($_13076 === \false) { return \false; }
}

public function criteria__finalise (&$result) {
        $result['data'] = [
            'type' => 'criteria',
            'leftOperand' => $result['field']['data'],
            ...$result['op']['data'],
        ];
        unset($result['field'], $result['op']);
    }

/* builtin_field: "@" identifier */
protected $match_builtin_field_typestack = ['builtin_field'];
function match_builtin_field($stack = []) {
	$matchrule = 'builtin_field';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13080 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '@') {
			$this->addPos(1);
			$result["text"] .= '@';
		}
		else { $_13080 = \false; break; }
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_13080 = \false; break; }
		$_13080 = \true; break;
	}
	while(\false);
	if($_13080 === \true) { return $this->finalise($result); }
	if($_13080 === \false) { return \false; }
}

public function builtin_field__finalise (&$result) {
        $result['data'] = ['field' => $result['text']];
    }

/* field_name: identifier */
protected $match_field_name_typestack = ['field_name'];
function match_field_name($stack = []) {
	$matchrule = 'field_name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'identifier'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres);
		return $this->finalise($result);
	}
	else { return \false; }
}

public function field_name__finalise (&$result) {
        $result['data'] = ['field' => $result['text']];
    }

/* field: f:builtin_field | f:field_name */
protected $match_field_typestack = ['field'];
function match_field($stack = []) {
	$matchrule = 'field';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13086 = \null;
	do {
		$res_13083 = $result;
		$pos_13083 = $this->pos;
		$key = 'match_'.'builtin_field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_13086 = \true; break;
		}
		$result = $res_13083;
		$this->setPos($pos_13083);
		$key = 'match_'.'field_name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_13086 = \true; break;
		}
		$result = $res_13083;
		$this->setPos($pos_13083);
		$_13086 = \false; break;
	}
	while(\false);
	if($_13086 === \true) { return $this->finalise($result); }
	if($_13086 === \false) { return \false; }
}

public function field__finalise (&$result) {
        $result['data'] = $result['f']['data'];
        unset($result['f']);
    }

/* boolean: "true" | "false" */
protected $match_boolean_typestack = ['boolean'];
function match_boolean($stack = []) {
	$matchrule = 'boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13091 = \null;
	do {
		$res_13088 = $result;
		$pos_13088 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_13091 = \true; break;
		}
		$result = $res_13088;
		$this->setPos($pos_13088);
		if (($subres = $this->literal('false')) !== \false) {
			$result["text"] .= $subres;
			$_13091 = \true; break;
		}
		$result = $res_13088;
		$this->setPos($pos_13088);
		$_13091 = \false; break;
	}
	while(\false);
	if($_13091 === \true) { return $this->finalise($result); }
	if($_13091 === \false) { return \false; }
}

public function boolean__finalise (&$result) {
        $result['data'] = $result['text'] === 'true';
    }

/* operator: ] op:between_operator | ] op:in_operator | ] op:ending_operator | > op:simple_operator | > op:keyword_operator */
protected $match_operator_typestack = ['operator'];
function match_operator($stack = []) {
	$matchrule = 'operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13123 = \null;
	do {
		$res_13093 = $result;
		$pos_13093 = $this->pos;
		$_13096 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_13096 = \false; break; }
			$key = 'match_'.'between_operator'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "op");
			}
			else { $_13096 = \false; break; }
			$_13096 = \true; break;
		}
		while(\false);
		if($_13096 === \true) { $_13123 = \true; break; }
		$result = $res_13093;
		$this->setPos($pos_13093);
		$_13121 = \null;
		do {
			$res_13098 = $result;
			$pos_13098 = $this->pos;
			$_13101 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_13101 = \false; break; }
				$key = 'match_'.'in_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "op");
				}
				else { $_13101 = \false; break; }
				$_13101 = \true; break;
			}
			while(\false);
			if($_13101 === \true) { $_13121 = \true; break; }
			$result = $res_13098;
			$this->setPos($pos_13098);
			$_13119 = \null;
			do {
				$res_13103 = $result;
				$pos_13103 = $this->pos;
				$_13106 = \null;
				do {
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					else { $_13106 = \false; break; }
					$key = 'match_'.'ending_operator'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "op");
					}
					else { $_13106 = \false; break; }
					$_13106 = \true; break;
				}
				while(\false);
				if($_13106 === \true) { $_13119 = \true; break; }
				$result = $res_13103;
				$this->setPos($pos_13103);
				$_13117 = \null;
				do {
					$res_13108 = $result;
					$pos_13108 = $this->pos;
					$_13111 = \null;
					do {
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$key = 'match_'.'simple_operator'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "op");
						}
						else { $_13111 = \false; break; }
						$_13111 = \true; break;
					}
					while(\false);
					if($_13111 === \true) { $_13117 = \true; break; }
					$result = $res_13108;
					$this->setPos($pos_13108);
					$_13115 = \null;
					do {
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$key = 'match_'.'keyword_operator'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "op");
						}
						else { $_13115 = \false; break; }
						$_13115 = \true; break;
					}
					while(\false);
					if($_13115 === \true) { $_13117 = \true; break; }
					$result = $res_13108;
					$this->setPos($pos_13108);
					$_13117 = \false; break;
				}
				while(\false);
				if($_13117 === \true) { $_13119 = \true; break; }
				$result = $res_13103;
				$this->setPos($pos_13103);
				$_13119 = \false; break;
			}
			while(\false);
			if($_13119 === \true) { $_13121 = \true; break; }
			$result = $res_13098;
			$this->setPos($pos_13098);
			$_13121 = \false; break;
		}
		while(\false);
		if($_13121 === \true) { $_13123 = \true; break; }
		$result = $res_13093;
		$this->setPos($pos_13093);
		$_13123 = \false; break;
	}
	while(\false);
	if($_13123 === \true) { return $this->finalise($result); }
	if($_13123 === \false) { return \false; }
}

public function operator__finalise (&$result) {
        $result['data'] = $result['op']['data'];
        unset($result['op']);
    }

/* between_operator: not:("NOT" ])? "BETWEEN" ] left:value_expression ] "AND" ] right:value_expression */
protected $match_between_operator_typestack = ['between_operator'];
function match_between_operator($stack = []) {
	$matchrule = 'between_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13136 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_13128 = $result;
		$pos_13128 = $this->pos;
		$_13127 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_13127 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_13127 = \false; break; }
			$_13127 = \true; break;
		}
		while(\false);
		if($_13127 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_13127 === \false) {
			$result = $res_13128;
			$this->setPos($pos_13128);
			unset($res_13128, $pos_13128);
		}
		if (($subres = $this->literal('BETWEEN')) !== \false) { $result["text"] .= $subres; }
		else { $_13136 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_13136 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_13136 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_13136 = \false; break; }
		if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
		else { $_13136 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_13136 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "right");
		}
		else { $_13136 = \false; break; }
		$_13136 = \true; break;
	}
	while(\false);
	if($_13136 === \true) { return $this->finalise($result); }
	if($_13136 === \false) { return \false; }
}

public function between_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => isset($result['not']) ? 'NOT_BETWEEN' : 'BETWEEN',
            'rightOperand' => [$result['left']['data'], $result['right']['data']],
        ];
        unset($result['left'], $result['right']);
    }

/* ending_operator: ("IS" ] "MISSING") | "EXISTS" */
protected $match_ending_operator_typestack = ['ending_operator'];
function match_ending_operator($stack = []) {
	$matchrule = 'ending_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13145 = \null;
	do {
		$res_13138 = $result;
		$pos_13138 = $this->pos;
		$_13142 = \null;
		do {
			if (($subres = $this->literal('IS')) !== \false) { $result["text"] .= $subres; }
			else { $_13142 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_13142 = \false; break; }
			if (($subres = $this->literal('MISSING')) !== \false) { $result["text"] .= $subres; }
			else { $_13142 = \false; break; }
			$_13142 = \true; break;
		}
		while(\false);
		if($_13142 === \true) { $_13145 = \true; break; }
		$result = $res_13138;
		$this->setPos($pos_13138);
		if (($subres = $this->literal('EXISTS')) !== \false) {
			$result["text"] .= $subres;
			$_13145 = \true; break;
		}
		$result = $res_13138;
		$this->setPos($pos_13138);
		$_13145 = \false; break;
	}
	while(\false);
	if($_13145 === \true) { return $this->finalise($result); }
	if($_13145 === \false) { return \false; }
}

public function ending_operator__finalise (&$result) {
        $assoc = [
            'IS_MISSING' => 'MISSING',
            'EXISTS' => 'EXISTS',
        ];
        $result['data'] = [
            'operator' => $assoc[preg_replace('#\s+#', '_', $result['text'])],
        ];
    }

/* in_operator: not:("NOT" ] )? "IN" > "(" > first:value_expression (> "," > others:value_expression)* > ")" */
protected $match_in_operator_typestack = ['in_operator'];
function match_in_operator($stack = []) {
	$matchrule = 'in_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13164 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_13150 = $result;
		$pos_13150 = $this->pos;
		$_13149 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_13149 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_13149 = \false; break; }
			$_13149 = \true; break;
		}
		while(\false);
		if($_13149 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_13149 === \false) {
			$result = $res_13150;
			$this->setPos($pos_13150);
			unset($res_13150, $pos_13150);
		}
		if (($subres = $this->literal('IN')) !== \false) { $result["text"] .= $subres; }
		else { $_13164 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_13164 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else { $_13164 = \false; break; }
		while (\true) {
			$res_13161 = $result;
			$pos_13161 = $this->pos;
			$_13160 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_13160 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_13160 = \false; break; }
				$_13160 = \true; break;
			}
			while(\false);
			if($_13160 === \false) {
				$result = $res_13161;
				$this->setPos($pos_13161);
				unset($res_13161, $pos_13161);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_13164 = \false; break; }
		$_13164 = \true; break;
	}
	while(\false);
	if($_13164 === \true) { return $this->finalise($result); }
	if($_13164 === \false) { return \false; }
}

public function in_operator__finalise (&$result) {
        $values = [$result['first']['data']];
        if (isset($result['others']['_matchrule'])) {
            $values[] = $result['others']['data'];
        } else {
            foreach ($result['others'] ?? [] as $v) {
                $values[] = $v['data'];
            }
        }
        $result['data'] = [
            'operator' => isset($result['not']) ? 'NOT_IN' : 'IN',
            'rightOperand' => $values,
        ];
        unset($result['first'], $result['others']);
    }

/* simple_operator: op:/([<>]?=|!=|[<>])/ > v:value_expression */
protected $match_simple_operator_typestack = ['simple_operator'];
function match_simple_operator($stack = []) {
	$matchrule = 'simple_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13169 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		if (($subres = $this->rx('/([<>]?=|!=|[<>])/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		else {
			$result = \array_pop($stack);
			$_13169 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_13169 = \false; break; }
		$_13169 = \true; break;
	}
	while(\false);
	if($_13169 === \true) { return $this->finalise($result); }
	if($_13169 === \false) { return \false; }
}

public function simple_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => preg_replace('#\s+#', '_', $result['op']['text']),
            'rightOperand' => $result['v']['data'],
        ];
        unset($result['op'], $result['v']);
    }

/* keyword_operator: op:op_keyword ] v:value_expression */
protected $match_keyword_operator_typestack = ['keyword_operator'];
function match_keyword_operator($stack = []) {
	$matchrule = 'keyword_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13174 = \null;
	do {
		$key = 'match_'.'op_keyword'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_13174 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_13174 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_13174 = \false; break; }
		$_13174 = \true; break;
	}
	while(\false);
	if($_13174 === \true) { return $this->finalise($result); }
	if($_13174 === \false) { return \false; }
}

public function keyword_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => $result['op']['data'],
            'rightOperand' => $result['v']['data'],
        ];
        unset($result['op'], $result['v']);
    }

/* op_keyword: not:/(DO(ES)?\s+NOT\s+)/? key:/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH)/ */
protected $match_op_keyword_typestack = ['op_keyword'];
function match_op_keyword($stack = []) {
	$matchrule = 'op_keyword';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13178 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_13176 = $result;
		$pos_13176 = $this->pos;
		if (($subres = $this->rx('/(DO(ES)?\s+NOT\s+)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		else {
			$result = $res_13176;
			$this->setPos($pos_13176);
			unset($res_13176, $pos_13176);
		}
		$stack[] = $result; $result = $this->construct($matchrule, "key");
		if (($subres = $this->rx('/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'key');
		}
		else {
			$result = \array_pop($stack);
			$_13178 = \false; break;
		}
		$_13178 = \true; break;
	}
	while(\false);
	if($_13178 === \true) { return $this->finalise($result); }
	if($_13178 === \false) { return \false; }
}

public function op_keyword__finalise (&$result) {
        $key = preg_replace('#\s+#', '_', $result['key']['text']);
        $result['data'] = (isset($result['not']) ? 'NOT_' : '').match ($key) {
            'CONTAINS', 'CONTAIN' => 'CONTAINS',
            'MATCHES', 'MATCH' => 'MATCHES',
            'STARTS_WITH', 'START_WITH' => 'STARTS_WITH',
        };
        unset($result['not'], $result['key']);
    }

/* function_call: f:identifier > "(" > first:value_expression? (> "," > others:value_expression)* > ")" */
protected $match_function_call_typestack = ['function_call'];
function match_function_call($stack = []) {
	$matchrule = 'function_call';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13193 = \null;
	do {
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "f"); }
		else { $_13193 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_13193 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$res_13184 = $result;
		$pos_13184 = $this->pos;
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else {
			$result = $res_13184;
			$this->setPos($pos_13184);
			unset($res_13184, $pos_13184);
		}
		while (\true) {
			$res_13190 = $result;
			$pos_13190 = $this->pos;
			$_13189 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_13189 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_13189 = \false; break; }
				$_13189 = \true; break;
			}
			while(\false);
			if($_13189 === \false) {
				$result = $res_13190;
				$this->setPos($pos_13190);
				unset($res_13190, $pos_13190);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_13193 = \false; break; }
		$_13193 = \true; break;
	}
	while(\false);
	if($_13193 === \true) { return $this->finalise($result); }
	if($_13193 === \false) { return \false; }
}

public function function_call__finalise (&$result) {
        \App\Elasticsearch\AQL\AQLFunctionHandler::parseFunction($result);
    }

/* value_expression: v:value_sum */
protected $match_value_expression_typestack = ['value_expression'];
function match_value_expression($stack = []) {
	$matchrule = 'value_expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'value_sum'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres, "v");
		return $this->finalise($result);
	}
	else { return \false; }
}

public function value_expression__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* value_product: v:value_or_expr ( > sign:('/' | '*') > right:value_or_expr ) * */
protected $match_value_product_typestack = ['value_product'];
function match_value_product($stack = []) {
	$matchrule = 'value_product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13209 = \null;
	do {
		$key = 'match_'.'value_or_expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_13209 = \false; break; }
		while (\true) {
			$res_13208 = $result;
			$pos_13208 = $this->pos;
			$_13207 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_13203 = \null;
				do {
					$_13201 = \null;
					do {
						$res_13198 = $result;
						$pos_13198 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '/') {
							$this->addPos(1);
							$result["text"] .= '/';
							$_13201 = \true; break;
						}
						$result = $res_13198;
						$this->setPos($pos_13198);
						if (\substr($this->string, $this->pos, 1) === '*') {
							$this->addPos(1);
							$result["text"] .= '*';
							$_13201 = \true; break;
						}
						$result = $res_13198;
						$this->setPos($pos_13198);
						$_13201 = \false; break;
					}
					while(\false);
					if($_13201 === \false) { $_13203 = \false; break; }
					$_13203 = \true; break;
				}
				while(\false);
				if($_13203 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_13203 === \false) {
					$result = \array_pop($stack);
					$_13207 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_or_expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_13207 = \false; break; }
				$_13207 = \true; break;
			}
			while(\false);
			if($_13207 === \false) {
				$result = $res_13208;
				$this->setPos($pos_13208);
				unset($res_13208, $pos_13208);
				break;
			}
		}
		$_13209 = \true; break;
	}
	while(\false);
	if($_13209 === \true) { return $this->finalise($result); }
	if($_13209 === \false) { return \false; }
}

public function value_product_handleOperator (mixed $l, mixed $r, string $operator): array|int|float {
        if (is_int($l) && is_int($r)) {
            return match ($operator) {
                '*' => $l * $r,
                '/' => $l / $r,
            };
        }
        return [
            'type' => 'value_expression',
            'operator' => $operator,
            'leftOperand' => $l,
            'rightOperand' => $r,
        ];
    }

public function value_product__finalise (&$result) {
        $l = $result['v']['data'];
        if (isset($result['sign'])) {
            if (isset($result['right']['_matchrule'])) {
                $result['data'] = $this->value_product_handleOperator($l, $result['right']['data'], $result['sign']['text']);
            } else {
                foreach ($result['right'] ?? [] as $k => $right) {
                    $l = $this->value_product_handleOperator($l, $right['data'], $result['sign'][$k]['text']);
                }
                $result['data'] = $l;
            }
            unset($result['sign'], $result['v'], $result['right']);
            return;
        }
        $result['data'] = $l;
        unset($result['v']);
    }

/* value_sum: v:value_product ( > sign:('+' | '-') > right:value_product ) * */
protected $match_value_sum_typestack = ['value_sum'];
function match_value_sum($stack = []) {
	$matchrule = 'value_sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13224 = \null;
	do {
		$key = 'match_'.'value_product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_13224 = \false; break; }
		while (\true) {
			$res_13223 = $result;
			$pos_13223 = $this->pos;
			$_13222 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_13218 = \null;
				do {
					$_13216 = \null;
					do {
						$res_13213 = $result;
						$pos_13213 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '+') {
							$this->addPos(1);
							$result["text"] .= '+';
							$_13216 = \true; break;
						}
						$result = $res_13213;
						$this->setPos($pos_13213);
						if (\substr($this->string, $this->pos, 1) === '-') {
							$this->addPos(1);
							$result["text"] .= '-';
							$_13216 = \true; break;
						}
						$result = $res_13213;
						$this->setPos($pos_13213);
						$_13216 = \false; break;
					}
					while(\false);
					if($_13216 === \false) { $_13218 = \false; break; }
					$_13218 = \true; break;
				}
				while(\false);
				if($_13218 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_13218 === \false) {
					$result = \array_pop($stack);
					$_13222 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_product'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_13222 = \false; break; }
				$_13222 = \true; break;
			}
			while(\false);
			if($_13222 === \false) {
				$result = $res_13223;
				$this->setPos($pos_13223);
				unset($res_13223, $pos_13223);
				break;
			}
		}
		$_13224 = \true; break;
	}
	while(\false);
	if($_13224 === \true) { return $this->finalise($result); }
	if($_13224 === \false) { return \false; }
}

public function value_sum_handleOperator (mixed $l, mixed $r, string $operator): array|int|float {
        if (is_int($l) && is_int($r)) {
            return match ($operator) {
                '+' => $l + $r,
                '-' => $l - $r,
            };
        }
        return [
            'type' => 'value_expression',
            'operator' => $operator,
            'leftOperand' => $l,
            'rightOperand' => $r,
        ];
    }

public function value_sum__finalise (&$result) {
        $l = $result['v']['data'];
        if (isset($result['sign'])) {
            if (isset($result['right']['_matchrule'])) {
                $result['data'] = $this->value_sum_handleOperator($l, $result['right']['data'], $result['sign']['text']);
            } else {
                foreach ($result['right'] ?? [] as $k => $right) {
                    $l = $this->value_sum_handleOperator($l, $right['data'], $result['sign'][$k]['text']);
                }
                $result['data'] = $l;
            }
            unset($result['sign'], $result['v'], $result['right']);
            return;
        }
        $result['data'] = $l;
        unset($result['v']);
    }

/* value_or_expr: v:value | '(' > v:value_expression > ')' */
protected $match_value_or_expr_typestack = ['value_or_expr'];
function match_value_or_expr($stack = []) {
	$matchrule = 'value_or_expr';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13235 = \null;
	do {
		$res_13226 = $result;
		$pos_13226 = $this->pos;
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_13235 = \true; break;
		}
		$result = $res_13226;
		$this->setPos($pos_13226);
		$_13233 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_13233 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'value_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "v");
			}
			else { $_13233 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_13233 = \false; break; }
			$_13233 = \true; break;
		}
		while(\false);
		if($_13233 === \true) { $_13235 = \true; break; }
		$result = $res_13226;
		$this->setPos($pos_13226);
		$_13235 = \false; break;
	}
	while(\false);
	if($_13235 === \true) { return $this->finalise($result); }
	if($_13235 === \false) { return \false; }
}

public function value_or_expr__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* value: v:function_call | v:number | v:quoted_string | v:boolean | v:field */
protected $match_value_typestack = ['value'];
function match_value($stack = []) {
	$matchrule = 'value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13252 = \null;
	do {
		$res_13237 = $result;
		$pos_13237 = $this->pos;
		$key = 'match_'.'function_call'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_13252 = \true; break;
		}
		$result = $res_13237;
		$this->setPos($pos_13237);
		$_13250 = \null;
		do {
			$res_13239 = $result;
			$pos_13239 = $this->pos;
			$key = 'match_'.'number'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "v");
				$_13250 = \true; break;
			}
			$result = $res_13239;
			$this->setPos($pos_13239);
			$_13248 = \null;
			do {
				$res_13241 = $result;
				$pos_13241 = $this->pos;
				$key = 'match_'.'quoted_string'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_13248 = \true; break;
				}
				$result = $res_13241;
				$this->setPos($pos_13241);
				$_13246 = \null;
				do {
					$res_13243 = $result;
					$pos_13243 = $this->pos;
					$key = 'match_'.'boolean'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "v");
						$_13246 = \true; break;
					}
					$result = $res_13243;
					$this->setPos($pos_13243);
					$key = 'match_'.'field'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "v");
						$_13246 = \true; break;
					}
					$result = $res_13243;
					$this->setPos($pos_13243);
					$_13246 = \false; break;
				}
				while(\false);
				if($_13246 === \true) { $_13248 = \true; break; }
				$result = $res_13241;
				$this->setPos($pos_13241);
				$_13248 = \false; break;
			}
			while(\false);
			if($_13248 === \true) { $_13250 = \true; break; }
			$result = $res_13239;
			$this->setPos($pos_13239);
			$_13250 = \false; break;
		}
		while(\false);
		if($_13250 === \true) { $_13252 = \true; break; }
		$result = $res_13237;
		$this->setPos($pos_13237);
		$_13252 = \false; break;
	}
	while(\false);
	if($_13252 === \true) { return $this->finalise($result); }
	if($_13252 === \false) { return \false; }
}

public function value__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* int: /[0-9]+/ */
protected $match_int_typestack = ['int'];
function match_int($stack = []) {
	$matchrule = 'int';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[0-9]+/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}

public function int__finalise (&$result) {
        $result['data'] = (int) $result['text'];
    }

/* decimal: int? "." int */
protected $match_decimal_typestack = ['decimal'];
function match_decimal($stack = []) {
	$matchrule = 'decimal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13258 = \null;
	do {
		$res_13255 = $result;
		$pos_13255 = $this->pos;
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else {
			$result = $res_13255;
			$this->setPos($pos_13255);
			unset($res_13255, $pos_13255);
		}
		if (\substr($this->string, $this->pos, 1) === '.') {
			$this->addPos(1);
			$result["text"] .= '.';
		}
		else { $_13258 = \false; break; }
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_13258 = \false; break; }
		$_13258 = \true; break;
	}
	while(\false);
	if($_13258 === \true) { return $this->finalise($result); }
	if($_13258 === \false) { return \false; }
}

public function decimal__finalise (&$result) {
        $result['data'] = (float) $result['text'];
    }

/* quoted_string: /"[^"]*"/ */
protected $match_quoted_string_typestack = ['quoted_string'];
function match_quoted_string($stack = []) {
	$matchrule = 'quoted_string';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/"[^"]*"/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}

public function quoted_string__finalise (&$result) {
        $result['data'] = ['literal' => substr($result['text'], 1, -1)];
    }

/* number: v:int | v:decimal */
protected $match_number_typestack = ['number'];
function match_number($stack = []) {
	$matchrule = 'number';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13264 = \null;
	do {
		$res_13261 = $result;
		$pos_13261 = $this->pos;
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_13264 = \true; break;
		}
		$result = $res_13261;
		$this->setPos($pos_13261);
		$key = 'match_'.'decimal'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_13264 = \true; break;
		}
		$result = $res_13261;
		$this->setPos($pos_13261);
		$_13264 = \false; break;
	}
	while(\false);
	if($_13264 === \true) { return $this->finalise($result); }
	if($_13264 === \false) { return \false; }
}

public function number__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* alpha: /[a-zA-Z_]/ */
protected $match_alpha_typestack = ['alpha'];
function match_alpha($stack = []) {
	$matchrule = 'alpha';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[a-zA-Z_]/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* alphanum: /[a-zA-Z_0-9-]/ */
protected $match_alphanum_typestack = ['alphanum'];
function match_alphanum($stack = []) {
	$matchrule = 'alphanum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[a-zA-Z_0-9-]/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* identifier: alpha alphanum* */
protected $match_identifier_typestack = ['identifier'];
function match_identifier($stack = []) {
	$matchrule = 'identifier';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_13270 = \null;
	do {
		$key = 'match_'.'alpha'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_13270 = \false; break; }
		while (\true) {
			$res_13269 = $result;
			$pos_13269 = $this->pos;
			$key = 'match_'.'alphanum'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_13269;
				$this->setPos($pos_13269);
				unset($res_13269, $pos_13269);
				break;
			}
		}
		$_13270 = \true; break;
	}
	while(\false);
	if($_13270 === \true) { return $this->finalise($result); }
	if($_13270 === \false) { return \false; }
}



}
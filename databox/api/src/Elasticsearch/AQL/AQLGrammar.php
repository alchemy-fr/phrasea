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
	$_11126 = \null;
	do {
		$key = 'match_'.'and_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_11126 = \false; break; }
		while (\true) {
			$res_11125 = $result;
			$pos_11125 = $this->pos;
			$_11124 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_11124 = \false; break; }
				if (($subres = $this->literal('OR')) !== \false) { $result["text"] .= $subres; }
				else { $_11124 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_11124 = \false; break; }
				$key = 'match_'.'and_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_11124 = \false; break; }
				$_11124 = \true; break;
			}
			while(\false);
			if($_11124 === \false) {
				$result = $res_11125;
				$this->setPos($pos_11125);
				unset($res_11125, $pos_11125);
				break;
			}
		}
		$_11126 = \true; break;
	}
	while(\false);
	if($_11126 === \true) { return $this->finalise($result); }
	if($_11126 === \false) { return \false; }
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
	$_11135 = \null;
	do {
		$key = 'match_'.'condition'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_11135 = \false; break; }
		while (\true) {
			$res_11134 = $result;
			$pos_11134 = $this->pos;
			$_11133 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_11133 = \false; break; }
				if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
				else { $_11133 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_11133 = \false; break; }
				$key = 'match_'.'condition'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_11133 = \false; break; }
				$_11133 = \true; break;
			}
			while(\false);
			if($_11133 === \false) {
				$result = $res_11134;
				$this->setPos($pos_11134);
				unset($res_11134, $pos_11134);
				break;
			}
		}
		$_11135 = \true; break;
	}
	while(\false);
	if($_11135 === \true) { return $this->finalise($result); }
	if($_11135 === \false) { return \false; }
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
	$_11150 = \null;
	do {
		$res_11137 = $result;
		$pos_11137 = $this->pos;
		$_11143 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_11143 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
			}
			else { $_11143 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_11143 = \false; break; }
			$_11143 = \true; break;
		}
		while(\false);
		if($_11143 === \true) { $_11150 = \true; break; }
		$result = $res_11137;
		$this->setPos($pos_11137);
		$_11148 = \null;
		do {
			$res_11145 = $result;
			$pos_11145 = $this->pos;
			$key = 'match_'.'not_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_11148 = \true; break;
			}
			$result = $res_11145;
			$this->setPos($pos_11145);
			$key = 'match_'.'criteria'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_11148 = \true; break;
			}
			$result = $res_11145;
			$this->setPos($pos_11145);
			$_11148 = \false; break;
		}
		while(\false);
		if($_11148 === \true) { $_11150 = \true; break; }
		$result = $res_11137;
		$this->setPos($pos_11137);
		$_11150 = \false; break;
	}
	while(\false);
	if($_11150 === \true) { return $this->finalise($result); }
	if($_11150 === \false) { return \false; }
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
	$_11155 = \null;
	do {
		if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
		else { $_11155 = \false; break; }
		$key = 'match_'.'__'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_11155 = \false; break; }
		$key = 'match_'.'expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "e"); }
		else { $_11155 = \false; break; }
		$_11155 = \true; break;
	}
	while(\false);
	if($_11155 === \true) { return $this->finalise($result); }
	if($_11155 === \false) { return \false; }
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
	$_11159 = \null;
	do {
		$key = 'match_'.'field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "field");
		}
		else { $_11159 = \false; break; }
		$key = 'match_'.'operator'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_11159 = \false; break; }
		$_11159 = \true; break;
	}
	while(\false);
	if($_11159 === \true) { return $this->finalise($result); }
	if($_11159 === \false) { return \false; }
}

public function criteria__finalise (&$result) {
        $result['data'] = [
            'type' => 'criteria',
            'leftOperand' => $result['field']['data'],
            ...$result['op']['data'],
        ];
        unset($result['field'], $result['op']);
    }

/* builtin_field: "@" keyword */
protected $match_builtin_field_typestack = ['builtin_field'];
function match_builtin_field($stack = []) {
	$matchrule = 'builtin_field';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_11163 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '@') {
			$this->addPos(1);
			$result["text"] .= '@';
		}
		else { $_11163 = \false; break; }
		$key = 'match_'.'keyword'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_11163 = \false; break; }
		$_11163 = \true; break;
	}
	while(\false);
	if($_11163 === \true) { return $this->finalise($result); }
	if($_11163 === \false) { return \false; }
}

public function builtin_field__finalise (&$result) {
        $result['data'] = ['field' => $result['text']];
    }

/* field_name: keyword */
protected $match_field_name_typestack = ['field_name'];
function match_field_name($stack = []) {
	$matchrule = 'field_name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'keyword'; $pos = $this->pos;
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
	$_11169 = \null;
	do {
		$res_11166 = $result;
		$pos_11166 = $this->pos;
		$key = 'match_'.'builtin_field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_11169 = \true; break;
		}
		$result = $res_11166;
		$this->setPos($pos_11166);
		$key = 'match_'.'field_name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_11169 = \true; break;
		}
		$result = $res_11166;
		$this->setPos($pos_11166);
		$_11169 = \false; break;
	}
	while(\false);
	if($_11169 === \true) { return $this->finalise($result); }
	if($_11169 === \false) { return \false; }
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
	$_11174 = \null;
	do {
		$res_11171 = $result;
		$pos_11171 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_11174 = \true; break;
		}
		$result = $res_11171;
		$this->setPos($pos_11171);
		if (($subres = $this->literal('false')) !== \false) {
			$result["text"] .= $subres;
			$_11174 = \true; break;
		}
		$result = $res_11171;
		$this->setPos($pos_11171);
		$_11174 = \false; break;
	}
	while(\false);
	if($_11174 === \true) { return $this->finalise($result); }
	if($_11174 === \false) { return \false; }
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
	$_11206 = \null;
	do {
		$res_11176 = $result;
		$pos_11176 = $this->pos;
		$_11179 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_11179 = \false; break; }
			$key = 'match_'.'between_operator'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "op");
			}
			else { $_11179 = \false; break; }
			$_11179 = \true; break;
		}
		while(\false);
		if($_11179 === \true) { $_11206 = \true; break; }
		$result = $res_11176;
		$this->setPos($pos_11176);
		$_11204 = \null;
		do {
			$res_11181 = $result;
			$pos_11181 = $this->pos;
			$_11184 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_11184 = \false; break; }
				$key = 'match_'.'in_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "op");
				}
				else { $_11184 = \false; break; }
				$_11184 = \true; break;
			}
			while(\false);
			if($_11184 === \true) { $_11204 = \true; break; }
			$result = $res_11181;
			$this->setPos($pos_11181);
			$_11202 = \null;
			do {
				$res_11186 = $result;
				$pos_11186 = $this->pos;
				$_11189 = \null;
				do {
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					else { $_11189 = \false; break; }
					$key = 'match_'.'ending_operator'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "op");
					}
					else { $_11189 = \false; break; }
					$_11189 = \true; break;
				}
				while(\false);
				if($_11189 === \true) { $_11202 = \true; break; }
				$result = $res_11186;
				$this->setPos($pos_11186);
				$_11200 = \null;
				do {
					$res_11191 = $result;
					$pos_11191 = $this->pos;
					$_11194 = \null;
					do {
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$key = 'match_'.'simple_operator'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "op");
						}
						else { $_11194 = \false; break; }
						$_11194 = \true; break;
					}
					while(\false);
					if($_11194 === \true) { $_11200 = \true; break; }
					$result = $res_11191;
					$this->setPos($pos_11191);
					$_11198 = \null;
					do {
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$key = 'match_'.'keyword_operator'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "op");
						}
						else { $_11198 = \false; break; }
						$_11198 = \true; break;
					}
					while(\false);
					if($_11198 === \true) { $_11200 = \true; break; }
					$result = $res_11191;
					$this->setPos($pos_11191);
					$_11200 = \false; break;
				}
				while(\false);
				if($_11200 === \true) { $_11202 = \true; break; }
				$result = $res_11186;
				$this->setPos($pos_11186);
				$_11202 = \false; break;
			}
			while(\false);
			if($_11202 === \true) { $_11204 = \true; break; }
			$result = $res_11181;
			$this->setPos($pos_11181);
			$_11204 = \false; break;
		}
		while(\false);
		if($_11204 === \true) { $_11206 = \true; break; }
		$result = $res_11176;
		$this->setPos($pos_11176);
		$_11206 = \false; break;
	}
	while(\false);
	if($_11206 === \true) { return $this->finalise($result); }
	if($_11206 === \false) { return \false; }
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
	$_11219 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_11211 = $result;
		$pos_11211 = $this->pos;
		$_11210 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_11210 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_11210 = \false; break; }
			$_11210 = \true; break;
		}
		while(\false);
		if($_11210 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_11210 === \false) {
			$result = $res_11211;
			$this->setPos($pos_11211);
			unset($res_11211, $pos_11211);
		}
		if (($subres = $this->literal('BETWEEN')) !== \false) { $result["text"] .= $subres; }
		else { $_11219 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_11219 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_11219 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_11219 = \false; break; }
		if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
		else { $_11219 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_11219 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "right");
		}
		else { $_11219 = \false; break; }
		$_11219 = \true; break;
	}
	while(\false);
	if($_11219 === \true) { return $this->finalise($result); }
	if($_11219 === \false) { return \false; }
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
	$_11228 = \null;
	do {
		$res_11221 = $result;
		$pos_11221 = $this->pos;
		$_11225 = \null;
		do {
			if (($subres = $this->literal('IS')) !== \false) { $result["text"] .= $subres; }
			else { $_11225 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_11225 = \false; break; }
			if (($subres = $this->literal('MISSING')) !== \false) { $result["text"] .= $subres; }
			else { $_11225 = \false; break; }
			$_11225 = \true; break;
		}
		while(\false);
		if($_11225 === \true) { $_11228 = \true; break; }
		$result = $res_11221;
		$this->setPos($pos_11221);
		if (($subres = $this->literal('EXISTS')) !== \false) {
			$result["text"] .= $subres;
			$_11228 = \true; break;
		}
		$result = $res_11221;
		$this->setPos($pos_11221);
		$_11228 = \false; break;
	}
	while(\false);
	if($_11228 === \true) { return $this->finalise($result); }
	if($_11228 === \false) { return \false; }
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
	$_11247 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_11233 = $result;
		$pos_11233 = $this->pos;
		$_11232 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_11232 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_11232 = \false; break; }
			$_11232 = \true; break;
		}
		while(\false);
		if($_11232 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_11232 === \false) {
			$result = $res_11233;
			$this->setPos($pos_11233);
			unset($res_11233, $pos_11233);
		}
		if (($subres = $this->literal('IN')) !== \false) { $result["text"] .= $subres; }
		else { $_11247 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_11247 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else { $_11247 = \false; break; }
		while (\true) {
			$res_11244 = $result;
			$pos_11244 = $this->pos;
			$_11243 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_11243 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_11243 = \false; break; }
				$_11243 = \true; break;
			}
			while(\false);
			if($_11243 === \false) {
				$result = $res_11244;
				$this->setPos($pos_11244);
				unset($res_11244, $pos_11244);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_11247 = \false; break; }
		$_11247 = \true; break;
	}
	while(\false);
	if($_11247 === \true) { return $this->finalise($result); }
	if($_11247 === \false) { return \false; }
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
	$_11252 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		if (($subres = $this->rx('/([<>]?=|!=|[<>])/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		else {
			$result = \array_pop($stack);
			$_11252 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_11252 = \false; break; }
		$_11252 = \true; break;
	}
	while(\false);
	if($_11252 === \true) { return $this->finalise($result); }
	if($_11252 === \false) { return \false; }
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
	$_11257 = \null;
	do {
		$key = 'match_'.'op_keyword'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_11257 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_11257 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_11257 = \false; break; }
		$_11257 = \true; break;
	}
	while(\false);
	if($_11257 === \true) { return $this->finalise($result); }
	if($_11257 === \false) { return \false; }
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
	$_11261 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_11259 = $result;
		$pos_11259 = $this->pos;
		if (($subres = $this->rx('/(DO(ES)?\s+NOT\s+)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		else {
			$result = $res_11259;
			$this->setPos($pos_11259);
			unset($res_11259, $pos_11259);
		}
		$stack[] = $result; $result = $this->construct($matchrule, "key");
		if (($subres = $this->rx('/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'key');
		}
		else {
			$result = \array_pop($stack);
			$_11261 = \false; break;
		}
		$_11261 = \true; break;
	}
	while(\false);
	if($_11261 === \true) { return $this->finalise($result); }
	if($_11261 === \false) { return \false; }
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
	$_11277 = \null;
	do {
		$key = 'match_'.'value_or_expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_11277 = \false; break; }
		while (\true) {
			$res_11276 = $result;
			$pos_11276 = $this->pos;
			$_11275 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_11271 = \null;
				do {
					$_11269 = \null;
					do {
						$res_11266 = $result;
						$pos_11266 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '/') {
							$this->addPos(1);
							$result["text"] .= '/';
							$_11269 = \true; break;
						}
						$result = $res_11266;
						$this->setPos($pos_11266);
						if (\substr($this->string, $this->pos, 1) === '*') {
							$this->addPos(1);
							$result["text"] .= '*';
							$_11269 = \true; break;
						}
						$result = $res_11266;
						$this->setPos($pos_11266);
						$_11269 = \false; break;
					}
					while(\false);
					if($_11269 === \false) { $_11271 = \false; break; }
					$_11271 = \true; break;
				}
				while(\false);
				if($_11271 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_11271 === \false) {
					$result = \array_pop($stack);
					$_11275 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_or_expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_11275 = \false; break; }
				$_11275 = \true; break;
			}
			while(\false);
			if($_11275 === \false) {
				$result = $res_11276;
				$this->setPos($pos_11276);
				unset($res_11276, $pos_11276);
				break;
			}
		}
		$_11277 = \true; break;
	}
	while(\false);
	if($_11277 === \true) { return $this->finalise($result); }
	if($_11277 === \false) { return \false; }
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
	$_11292 = \null;
	do {
		$key = 'match_'.'value_product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_11292 = \false; break; }
		while (\true) {
			$res_11291 = $result;
			$pos_11291 = $this->pos;
			$_11290 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_11286 = \null;
				do {
					$_11284 = \null;
					do {
						$res_11281 = $result;
						$pos_11281 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '+') {
							$this->addPos(1);
							$result["text"] .= '+';
							$_11284 = \true; break;
						}
						$result = $res_11281;
						$this->setPos($pos_11281);
						if (\substr($this->string, $this->pos, 1) === '-') {
							$this->addPos(1);
							$result["text"] .= '-';
							$_11284 = \true; break;
						}
						$result = $res_11281;
						$this->setPos($pos_11281);
						$_11284 = \false; break;
					}
					while(\false);
					if($_11284 === \false) { $_11286 = \false; break; }
					$_11286 = \true; break;
				}
				while(\false);
				if($_11286 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_11286 === \false) {
					$result = \array_pop($stack);
					$_11290 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_product'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_11290 = \false; break; }
				$_11290 = \true; break;
			}
			while(\false);
			if($_11290 === \false) {
				$result = $res_11291;
				$this->setPos($pos_11291);
				unset($res_11291, $pos_11291);
				break;
			}
		}
		$_11292 = \true; break;
	}
	while(\false);
	if($_11292 === \true) { return $this->finalise($result); }
	if($_11292 === \false) { return \false; }
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
	$_11303 = \null;
	do {
		$res_11294 = $result;
		$pos_11294 = $this->pos;
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_11303 = \true; break;
		}
		$result = $res_11294;
		$this->setPos($pos_11294);
		$_11301 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_11301 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'value_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "v");
			}
			else { $_11301 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_11301 = \false; break; }
			$_11301 = \true; break;
		}
		while(\false);
		if($_11301 === \true) { $_11303 = \true; break; }
		$result = $res_11294;
		$this->setPos($pos_11294);
		$_11303 = \false; break;
	}
	while(\false);
	if($_11303 === \true) { return $this->finalise($result); }
	if($_11303 === \false) { return \false; }
}

public function value_or_expr__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* value: v:number | v:quoted_string | v:boolean | v:field */
protected $match_value_typestack = ['value'];
function match_value($stack = []) {
	$matchrule = 'value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_11316 = \null;
	do {
		$res_11305 = $result;
		$pos_11305 = $this->pos;
		$key = 'match_'.'number'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_11316 = \true; break;
		}
		$result = $res_11305;
		$this->setPos($pos_11305);
		$_11314 = \null;
		do {
			$res_11307 = $result;
			$pos_11307 = $this->pos;
			$key = 'match_'.'quoted_string'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "v");
				$_11314 = \true; break;
			}
			$result = $res_11307;
			$this->setPos($pos_11307);
			$_11312 = \null;
			do {
				$res_11309 = $result;
				$pos_11309 = $this->pos;
				$key = 'match_'.'boolean'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_11312 = \true; break;
				}
				$result = $res_11309;
				$this->setPos($pos_11309);
				$key = 'match_'.'field'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_11312 = \true; break;
				}
				$result = $res_11309;
				$this->setPos($pos_11309);
				$_11312 = \false; break;
			}
			while(\false);
			if($_11312 === \true) { $_11314 = \true; break; }
			$result = $res_11307;
			$this->setPos($pos_11307);
			$_11314 = \false; break;
		}
		while(\false);
		if($_11314 === \true) { $_11316 = \true; break; }
		$result = $res_11305;
		$this->setPos($pos_11305);
		$_11316 = \false; break;
	}
	while(\false);
	if($_11316 === \true) { return $this->finalise($result); }
	if($_11316 === \false) { return \false; }
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
	$_11322 = \null;
	do {
		$res_11319 = $result;
		$pos_11319 = $this->pos;
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else {
			$result = $res_11319;
			$this->setPos($pos_11319);
			unset($res_11319, $pos_11319);
		}
		if (\substr($this->string, $this->pos, 1) === '.') {
			$this->addPos(1);
			$result["text"] .= '.';
		}
		else { $_11322 = \false; break; }
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_11322 = \false; break; }
		$_11322 = \true; break;
	}
	while(\false);
	if($_11322 === \true) { return $this->finalise($result); }
	if($_11322 === \false) { return \false; }
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
	$_11328 = \null;
	do {
		$res_11325 = $result;
		$pos_11325 = $this->pos;
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_11328 = \true; break;
		}
		$result = $res_11325;
		$this->setPos($pos_11325);
		$key = 'match_'.'decimal'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_11328 = \true; break;
		}
		$result = $res_11325;
		$this->setPos($pos_11325);
		$_11328 = \false; break;
	}
	while(\false);
	if($_11328 === \true) { return $this->finalise($result); }
	if($_11328 === \false) { return \false; }
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


/* keyword: alpha alphanum* */
protected $match_keyword_typestack = ['keyword'];
function match_keyword($stack = []) {
	$matchrule = 'keyword';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_11334 = \null;
	do {
		$key = 'match_'.'alpha'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_11334 = \false; break; }
		while (\true) {
			$res_11333 = $result;
			$pos_11333 = $this->pos;
			$key = 'match_'.'alphanum'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_11333;
				$this->setPos($pos_11333);
				unset($res_11333, $pos_11333);
				break;
			}
		}
		$_11334 = \true; break;
	}
	while(\false);
	if($_11334 === \true) { return $this->finalise($result); }
	if($_11334 === \false) { return \false; }
}



}
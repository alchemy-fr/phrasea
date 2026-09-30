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
	$_26983 = \null;
	do {
		$key = 'match_'.'and_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_26983 = \false; break; }
		while (\true) {
			$res_26982 = $result;
			$pos_26982 = $this->pos;
			$_26981 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_26981 = \false; break; }
				if (($subres = $this->literal('OR')) !== \false) { $result["text"] .= $subres; }
				else { $_26981 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_26981 = \false; break; }
				$key = 'match_'.'and_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_26981 = \false; break; }
				$_26981 = \true; break;
			}
			while(\false);
			if($_26981 === \false) {
				$result = $res_26982;
				$this->setPos($pos_26982);
				unset($res_26982, $pos_26982);
				break;
			}
		}
		$_26983 = \true; break;
	}
	while(\false);
	if($_26983 === \true) { return $this->finalise($result); }
	if($_26983 === \false) { return \false; }
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
	$_26992 = \null;
	do {
		$key = 'match_'.'condition'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_26992 = \false; break; }
		while (\true) {
			$res_26991 = $result;
			$pos_26991 = $this->pos;
			$_26990 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_26990 = \false; break; }
				if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
				else { $_26990 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_26990 = \false; break; }
				$key = 'match_'.'condition'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_26990 = \false; break; }
				$_26990 = \true; break;
			}
			while(\false);
			if($_26990 === \false) {
				$result = $res_26991;
				$this->setPos($pos_26991);
				unset($res_26991, $pos_26991);
				break;
			}
		}
		$_26992 = \true; break;
	}
	while(\false);
	if($_26992 === \true) { return $this->finalise($result); }
	if($_26992 === \false) { return \false; }
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

/* condition: '(' > e:expression > ')'
    | e:not_expression
    | e:criteria */
protected $match_condition_typestack = ['condition'];
function match_condition($stack = []) {
	$matchrule = 'condition';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27007 = \null;
	do {
		$res_26994 = $result;
		$pos_26994 = $this->pos;
		$_27000 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_27000 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
			}
			else { $_27000 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_27000 = \false; break; }
			$_27000 = \true; break;
		}
		while(\false);
		if($_27000 === \true) { $_27007 = \true; break; }
		$result = $res_26994;
		$this->setPos($pos_26994);
		$_27005 = \null;
		do {
			$res_27002 = $result;
			$pos_27002 = $this->pos;
			$key = 'match_'.'not_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_27005 = \true; break;
			}
			$result = $res_27002;
			$this->setPos($pos_27002);
			$key = 'match_'.'criteria'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_27005 = \true; break;
			}
			$result = $res_27002;
			$this->setPos($pos_27002);
			$_27005 = \false; break;
		}
		while(\false);
		if($_27005 === \true) { $_27007 = \true; break; }
		$result = $res_26994;
		$this->setPos($pos_26994);
		$_27007 = \false; break;
	}
	while(\false);
	if($_27007 === \true) { return $this->finalise($result); }
	if($_27007 === \false) { return \false; }
}

public function condition__finalise (&$result) {
        $result['data'] = $result['e']['data'];
        unset($result['e']);
    }

/* not_expression: "NOT" ] e:expression */
protected $match_not_expression_typestack = ['not_expression'];
function match_not_expression($stack = []) {
	$matchrule = 'not_expression';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27012 = \null;
	do {
		if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
		else { $_27012 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27012 = \false; break; }
		$key = 'match_'.'expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "e"); }
		else { $_27012 = \false; break; }
		$_27012 = \true; break;
	}
	while(\false);
	if($_27012 === \true) { return $this->finalise($result); }
	if($_27012 === \false) { return \false; }
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
	$_27016 = \null;
	do {
		$key = 'match_'.'field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "field");
		}
		else { $_27016 = \false; break; }
		$key = 'match_'.'operator'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_27016 = \false; break; }
		$_27016 = \true; break;
	}
	while(\false);
	if($_27016 === \true) { return $this->finalise($result); }
	if($_27016 === \false) { return \false; }
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
	$_27020 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '@') {
			$this->addPos(1);
			$result["text"] .= '@';
		}
		else { $_27020 = \false; break; }
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_27020 = \false; break; }
		$_27020 = \true; break;
	}
	while(\false);
	if($_27020 === \true) { return $this->finalise($result); }
	if($_27020 === \false) { return \false; }
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
	$_27026 = \null;
	do {
		$res_27023 = $result;
		$pos_27023 = $this->pos;
		$key = 'match_'.'builtin_field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_27026 = \true; break;
		}
		$result = $res_27023;
		$this->setPos($pos_27023);
		$key = 'match_'.'field_name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_27026 = \true; break;
		}
		$result = $res_27023;
		$this->setPos($pos_27023);
		$_27026 = \false; break;
	}
	while(\false);
	if($_27026 === \true) { return $this->finalise($result); }
	if($_27026 === \false) { return \false; }
}

public function field__finalise (&$result) {
        $result['data'] = $result['f']['data'];
        unset($result['f']);
    }

/* boolean: "true" | "false" | "TRUE" | "FALSE" */
protected $match_boolean_typestack = ['boolean'];
function match_boolean($stack = []) {
	$matchrule = 'boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27039 = \null;
	do {
		$res_27028 = $result;
		$pos_27028 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_27039 = \true; break;
		}
		$result = $res_27028;
		$this->setPos($pos_27028);
		$_27037 = \null;
		do {
			$res_27030 = $result;
			$pos_27030 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_27037 = \true; break;
			}
			$result = $res_27030;
			$this->setPos($pos_27030);
			$_27035 = \null;
			do {
				$res_27032 = $result;
				$pos_27032 = $this->pos;
				if (($subres = $this->literal('TRUE')) !== \false) {
					$result["text"] .= $subres;
					$_27035 = \true; break;
				}
				$result = $res_27032;
				$this->setPos($pos_27032);
				if (($subres = $this->literal('FALSE')) !== \false) {
					$result["text"] .= $subres;
					$_27035 = \true; break;
				}
				$result = $res_27032;
				$this->setPos($pos_27032);
				$_27035 = \false; break;
			}
			while(\false);
			if($_27035 === \true) { $_27037 = \true; break; }
			$result = $res_27030;
			$this->setPos($pos_27030);
			$_27037 = \false; break;
		}
		while(\false);
		if($_27037 === \true) { $_27039 = \true; break; }
		$result = $res_27028;
		$this->setPos($pos_27028);
		$_27039 = \false; break;
	}
	while(\false);
	if($_27039 === \true) { return $this->finalise($result); }
	if($_27039 === \false) { return \false; }
}

public function boolean__finalise (&$result) {
        $result['data'] = strtolower($result['text']) === 'true';
    }

/* const_null: "null" | "NULL" */
protected $match_const_null_typestack = ['const_null'];
function match_const_null($stack = []) {
	$matchrule = 'const_null';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27044 = \null;
	do {
		$res_27041 = $result;
		$pos_27041 = $this->pos;
		if (($subres = $this->literal('null')) !== \false) {
			$result["text"] .= $subres;
			$_27044 = \true; break;
		}
		$result = $res_27041;
		$this->setPos($pos_27041);
		if (($subres = $this->literal('NULL')) !== \false) {
			$result["text"] .= $subres;
			$_27044 = \true; break;
		}
		$result = $res_27041;
		$this->setPos($pos_27041);
		$_27044 = \false; break;
	}
	while(\false);
	if($_27044 === \true) { return $this->finalise($result); }
	if($_27044 === \false) { return \false; }
}

public function const_null__finalise (&$result) {
        $result['data'] = null;
    }

/* operator: ] op:between_operator | ] op:in_operator | ] op:geo_operator | ] op:ending_operator | ] op:is_operator | > op:simple_operator | > op:keyword_operator */
protected $match_operator_typestack = ['operator'];
function match_operator($stack = []) {
	$matchrule = 'operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27090 = \null;
	do {
		$res_27046 = $result;
		$pos_27046 = $this->pos;
		$_27049 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_27049 = \false; break; }
			$key = 'match_'.'between_operator'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "op");
			}
			else { $_27049 = \false; break; }
			$_27049 = \true; break;
		}
		while(\false);
		if($_27049 === \true) { $_27090 = \true; break; }
		$result = $res_27046;
		$this->setPos($pos_27046);
		$_27088 = \null;
		do {
			$res_27051 = $result;
			$pos_27051 = $this->pos;
			$_27054 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_27054 = \false; break; }
				$key = 'match_'.'in_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "op");
				}
				else { $_27054 = \false; break; }
				$_27054 = \true; break;
			}
			while(\false);
			if($_27054 === \true) { $_27088 = \true; break; }
			$result = $res_27051;
			$this->setPos($pos_27051);
			$_27086 = \null;
			do {
				$res_27056 = $result;
				$pos_27056 = $this->pos;
				$_27059 = \null;
				do {
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					else { $_27059 = \false; break; }
					$key = 'match_'.'geo_operator'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "op");
					}
					else { $_27059 = \false; break; }
					$_27059 = \true; break;
				}
				while(\false);
				if($_27059 === \true) { $_27086 = \true; break; }
				$result = $res_27056;
				$this->setPos($pos_27056);
				$_27084 = \null;
				do {
					$res_27061 = $result;
					$pos_27061 = $this->pos;
					$_27064 = \null;
					do {
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						else { $_27064 = \false; break; }
						$key = 'match_'.'ending_operator'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "op");
						}
						else { $_27064 = \false; break; }
						$_27064 = \true; break;
					}
					while(\false);
					if($_27064 === \true) { $_27084 = \true; break; }
					$result = $res_27061;
					$this->setPos($pos_27061);
					$_27082 = \null;
					do {
						$res_27066 = $result;
						$pos_27066 = $this->pos;
						$_27069 = \null;
						do {
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							else { $_27069 = \false; break; }
							$key = 'match_'.'is_operator'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres, "op");
							}
							else { $_27069 = \false; break; }
							$_27069 = \true; break;
						}
						while(\false);
						if($_27069 === \true) { $_27082 = \true; break; }
						$result = $res_27066;
						$this->setPos($pos_27066);
						$_27080 = \null;
						do {
							$res_27071 = $result;
							$pos_27071 = $this->pos;
							$_27074 = \null;
							do {
								if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
								$key = 'match_'.'simple_operator'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres, "op");
								}
								else { $_27074 = \false; break; }
								$_27074 = \true; break;
							}
							while(\false);
							if($_27074 === \true) { $_27080 = \true; break; }
							$result = $res_27071;
							$this->setPos($pos_27071);
							$_27078 = \null;
							do {
								if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
								$key = 'match_'.'keyword_operator'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres, "op");
								}
								else { $_27078 = \false; break; }
								$_27078 = \true; break;
							}
							while(\false);
							if($_27078 === \true) { $_27080 = \true; break; }
							$result = $res_27071;
							$this->setPos($pos_27071);
							$_27080 = \false; break;
						}
						while(\false);
						if($_27080 === \true) { $_27082 = \true; break; }
						$result = $res_27066;
						$this->setPos($pos_27066);
						$_27082 = \false; break;
					}
					while(\false);
					if($_27082 === \true) { $_27084 = \true; break; }
					$result = $res_27061;
					$this->setPos($pos_27061);
					$_27084 = \false; break;
				}
				while(\false);
				if($_27084 === \true) { $_27086 = \true; break; }
				$result = $res_27056;
				$this->setPos($pos_27056);
				$_27086 = \false; break;
			}
			while(\false);
			if($_27086 === \true) { $_27088 = \true; break; }
			$result = $res_27051;
			$this->setPos($pos_27051);
			$_27088 = \false; break;
		}
		while(\false);
		if($_27088 === \true) { $_27090 = \true; break; }
		$result = $res_27046;
		$this->setPos($pos_27046);
		$_27090 = \false; break;
	}
	while(\false);
	if($_27090 === \true) { return $this->finalise($result); }
	if($_27090 === \false) { return \false; }
}

public function operator__finalise (&$result) {
        $result['data'] = $result['op']['data'];
        unset($result['op']);
    }

/* geo_operator: "WITHIN" ] (v:within_circle_operator | v:within_rectangle_operator) */
protected $match_geo_operator_typestack = ['geo_operator'];
function match_geo_operator($stack = []) {
	$matchrule = 'geo_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27101 = \null;
	do {
		if (($subres = $this->literal('WITHIN')) !== \false) { $result["text"] .= $subres; }
		else { $_27101 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27101 = \false; break; }
		$_27099 = \null;
		do {
			$_27097 = \null;
			do {
				$res_27094 = $result;
				$pos_27094 = $this->pos;
				$key = 'match_'.'within_circle_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_27097 = \true; break;
				}
				$result = $res_27094;
				$this->setPos($pos_27094);
				$key = 'match_'.'within_rectangle_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_27097 = \true; break;
				}
				$result = $res_27094;
				$this->setPos($pos_27094);
				$_27097 = \false; break;
			}
			while(\false);
			if($_27097 === \false) { $_27099 = \false; break; }
			$_27099 = \true; break;
		}
		while(\false);
		if($_27099 === \false) { $_27101 = \false; break; }
		$_27101 = \true; break;
	}
	while(\false);
	if($_27101 === \true) { return $this->finalise($result); }
	if($_27101 === \false) { return \false; }
}

public function geo_operator__finalise (&$result) {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* within_circle_operator: "CIRCLE" > "(" > lat:value_expression > "," > lng:value_expression > "," > radius:value_expression > ")" */
protected $match_within_circle_operator_typestack = ['within_circle_operator'];
function match_within_circle_operator($stack = []) {
	$matchrule = 'within_circle_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27118 = \null;
	do {
		if (($subres = $this->literal('CIRCLE')) !== \false) { $result["text"] .= $subres; }
		else { $_27118 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_27118 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "lat");
		}
		else { $_27118 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27118 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "lng");
		}
		else { $_27118 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27118 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "radius");
		}
		else { $_27118 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_27118 = \false; break; }
		$_27118 = \true; break;
	}
	while(\false);
	if($_27118 === \true) { return $this->finalise($result); }
	if($_27118 === \false) { return \false; }
}

public function within_circle_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => 'WITHIN_CIRCLE',
            'rightOperand' => [
                $result['lat']['data'],
                $result['lng']['data'],
                $result['radius']['data'],
            ],
        ];
        unset($result['lat'], $result['lng'], $result['radius']);
    }

/* within_rectangle_operator: "RECTANGLE" > "(" > topLeftLat:value_expression > "," > topLeftLng:value_expression > "," > bottomRightLat:value_expression > "," > bottomRightLng:value_expression > ")" */
protected $match_within_rectangle_operator_typestack = ['within_rectangle_operator'];
function match_within_rectangle_operator($stack = []) {
	$matchrule = 'within_rectangle_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27139 = \null;
	do {
		if (($subres = $this->literal('RECTANGLE')) !== \false) { $result["text"] .= $subres; }
		else { $_27139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_27139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "topLeftLat");
		}
		else { $_27139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "topLeftLng");
		}
		else { $_27139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "bottomRightLat");
		}
		else { $_27139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_27139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "bottomRightLng");
		}
		else { $_27139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_27139 = \false; break; }
		$_27139 = \true; break;
	}
	while(\false);
	if($_27139 === \true) { return $this->finalise($result); }
	if($_27139 === \false) { return \false; }
}

public function within_rectangle_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => 'WITHIN_RECTANGLE',
            'rightOperand' => [
                $result['topLeftLat']['data'],
                $result['topLeftLng']['data'],
                $result['bottomRightLat']['data'],
                $result['bottomRightLng']['data'],
            ],
        ];
        unset($result['topLeftLat'], $result['topLeftLng'], $result['bottomRightLat'], $result['bottomRightLng']);
    }

/* between_operator: not:("NOT" ])? "BETWEEN" ] left:value_expression ] "AND" ] right:value_expression */
protected $match_between_operator_typestack = ['between_operator'];
function match_between_operator($stack = []) {
	$matchrule = 'between_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27152 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_27144 = $result;
		$pos_27144 = $this->pos;
		$_27143 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_27143 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_27143 = \false; break; }
			$_27143 = \true; break;
		}
		while(\false);
		if($_27143 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_27143 === \false) {
			$result = $res_27144;
			$this->setPos($pos_27144);
			unset($res_27144, $pos_27144);
		}
		if (($subres = $this->literal('BETWEEN')) !== \false) { $result["text"] .= $subres; }
		else { $_27152 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27152 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_27152 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27152 = \false; break; }
		if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
		else { $_27152 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27152 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "right");
		}
		else { $_27152 = \false; break; }
		$_27152 = \true; break;
	}
	while(\false);
	if($_27152 === \true) { return $this->finalise($result); }
	if($_27152 === \false) { return \false; }
}

public function between_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => isset($result['not']) ? 'NOT_BETWEEN' : 'BETWEEN',
            'rightOperand' => [$result['left']['data'], $result['right']['data']],
        ];
        unset($result['left'], $result['right']);
    }

/* ending_operator: op:/(IS\s+NOT\s+EMPTY|IS\s+EMPTY|IS\s+MISSING|EXISTS)\b/ */
protected $match_ending_operator_typestack = ['ending_operator'];
function match_ending_operator($stack = []) {
	$matchrule = 'ending_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$stack[] = $result; $result = $this->construct($matchrule, "op");
	if (($subres = $this->rx('/(IS\s+NOT\s+EMPTY|IS\s+EMPTY|IS\s+MISSING|EXISTS)\b/')) !== \false) {
		$result["text"] .= $subres;
		$subres = $result; $result = \array_pop($stack);
		$this->store($result, $subres, 'op');
		return $this->finalise($result);
	}
	else {
		$result = \array_pop($stack);
		return \false;
	}
}

public function ending_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => match (preg_replace('#\s+#', '_', $result['op']['text'])) {
                'IS_MISSING', 'IS_EMPTY' => 'MISSING',
                'EXISTS', 'IS_NOT_EMPTY' => 'EXISTS',
            },
        ];
        unset($result['op']);
    }

/* is_operator: "IS" ] not:("NOT" ])? !is_reserved v:value_expression */
protected $match_is_operator_typestack = ['is_operator'];
function match_is_operator($stack = []) {
	$matchrule = 'is_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27163 = \null;
	do {
		if (($subres = $this->literal('IS')) !== \false) { $result["text"] .= $subres; }
		else { $_27163 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27163 = \false; break; }
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_27160 = $result;
		$pos_27160 = $this->pos;
		$_27159 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_27159 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_27159 = \false; break; }
			$_27159 = \true; break;
		}
		while(\false);
		if($_27159 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_27159 === \false) {
			$result = $res_27160;
			$this->setPos($pos_27160);
			unset($res_27160, $pos_27160);
		}
		$res_27161 = $result;
		$pos_27161 = $this->pos;
		$key = 'match_'.'is_reserved'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$result = $res_27161;
			$this->setPos($pos_27161);
			$_27163 = \false; break;
		}
		else {
			$result = $res_27161;
			$this->setPos($pos_27161);
		}
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27163 = \false; break; }
		$_27163 = \true; break;
	}
	while(\false);
	if($_27163 === \true) { return $this->finalise($result); }
	if($_27163 === \false) { return \false; }
}

public function is_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => isset($result['not']) ? '!=' : '=',
            'rightOperand' => $result['v']['data'],
        ];
        unset($result['not'], $result['v']);
    }

/* is_reserved: /(EMPTY|MISSING|ANY|NONE)\b/ */
protected $match_is_reserved_typestack = ['is_reserved'];
function match_is_reserved($stack = []) {
	$matchrule = 'is_reserved';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/(EMPTY|MISSING|ANY|NONE)\b/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* in_operator: op:/(NOT\s+IN|IN|IS\s+ANY\s+OF|IS\s+NONE\s+OF|HAS\s+ANY\s+OF|HAS\s+NONE\s+OF|HAS\s+ALL\s+OF)\b/ > '(' > first:value_expression (> ',' > others:value_expression)* > ')' */
protected $match_in_operator_typestack = ['in_operator'];
function match_in_operator($stack = []) {
	$matchrule = 'in_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27179 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		if (($subres = $this->rx('/(NOT\s+IN|IN|IS\s+ANY\s+OF|IS\s+NONE\s+OF|HAS\s+ANY\s+OF|HAS\s+NONE\s+OF|HAS\s+ALL\s+OF)\b/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		else {
			$result = \array_pop($stack);
			$_27179 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_27179 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else { $_27179 = \false; break; }
		while (\true) {
			$res_27176 = $result;
			$pos_27176 = $this->pos;
			$_27175 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_27175 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_27175 = \false; break; }
				$_27175 = \true; break;
			}
			while(\false);
			if($_27175 === \false) {
				$result = $res_27176;
				$this->setPos($pos_27176);
				unset($res_27176, $pos_27176);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_27179 = \false; break; }
		$_27179 = \true; break;
	}
	while(\false);
	if($_27179 === \true) { return $this->finalise($result); }
	if($_27179 === \false) { return \false; }
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
            'operator' => match (preg_replace('#\s+#', '_', $result['op']['text'])) {
                'IN', 'IS_ANY_OF', 'HAS_ANY_OF' => 'IN',
                'NOT_IN', 'IS_NONE_OF', 'HAS_NONE_OF' => 'NOT_IN',
                'HAS_ALL_OF' => 'HAS_ALL_OF',
            },
            'rightOperand' => $values,
        ];
        unset($result['op'], $result['first'], $result['others']);
    }

/* simple_operator: op:/([<>]?=|!=|[<>])/ > v:value_expression */
protected $match_simple_operator_typestack = ['simple_operator'];
function match_simple_operator($stack = []) {
	$matchrule = 'simple_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27184 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		if (($subres = $this->rx('/([<>]?=|!=|[<>])/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		else {
			$result = \array_pop($stack);
			$_27184 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27184 = \false; break; }
		$_27184 = \true; break;
	}
	while(\false);
	if($_27184 === \true) { return $this->finalise($result); }
	if($_27184 === \false) { return \false; }
}

public function simple_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => preg_replace('#\s+#', '_', $result['op']['text']),
            'rightOperand' => $result['v']['data'],
        ];
        unset($result['op'], $result['v']);
    }

/* keyword_operator: op:op_keyword ] v:value_expression (] cs:case_sensitive)? */
protected $match_keyword_operator_typestack = ['keyword_operator'];
function match_keyword_operator($stack = []) {
	$matchrule = 'keyword_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27193 = \null;
	do {
		$key = 'match_'.'op_keyword'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_27193 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27193 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27193 = \false; break; }
		$res_27192 = $result;
		$pos_27192 = $this->pos;
		$_27191 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_27191 = \false; break; }
			$key = 'match_'.'case_sensitive'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "cs");
			}
			else { $_27191 = \false; break; }
			$_27191 = \true; break;
		}
		while(\false);
		if($_27191 === \false) {
			$result = $res_27192;
			$this->setPos($pos_27192);
			unset($res_27192, $pos_27192);
		}
		$_27193 = \true; break;
	}
	while(\false);
	if($_27193 === \true) { return $this->finalise($result); }
	if($_27193 === \false) { return \false; }
}

public function keyword_operator__finalise (&$result) {
        $result['data'] = [
            'operator' => $result['op']['data'],
            'rightOperand' => $result['v']['data'],
        ];
        if (isset($result['cs'])) {
            $result['data']['caseSensitive'] = true;
        }
        unset($result['op'], $result['v'], $result['cs']);
    }

/* case_sensitive: "CASE" ] "SENSITIVE" */
protected $match_case_sensitive_typestack = ['case_sensitive'];
function match_case_sensitive($stack = []) {
	$matchrule = 'case_sensitive';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27198 = \null;
	do {
		if (($subres = $this->literal('CASE')) !== \false) { $result["text"] .= $subres; }
		else { $_27198 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_27198 = \false; break; }
		if (($subres = $this->literal('SENSITIVE')) !== \false) { $result["text"] .= $subres; }
		else { $_27198 = \false; break; }
		$_27198 = \true; break;
	}
	while(\false);
	if($_27198 === \true) { return $this->finalise($result); }
	if($_27198 === \false) { return \false; }
}


/* op_keyword: not:/(DO(ES)?\s+NOT\s+)/? key:/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH|ENDS?\s+WITH)/ */
protected $match_op_keyword_typestack = ['op_keyword'];
function match_op_keyword($stack = []) {
	$matchrule = 'op_keyword';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27202 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_27200 = $result;
		$pos_27200 = $this->pos;
		if (($subres = $this->rx('/(DO(ES)?\s+NOT\s+)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		else {
			$result = $res_27200;
			$this->setPos($pos_27200);
			unset($res_27200, $pos_27200);
		}
		$stack[] = $result; $result = $this->construct($matchrule, "key");
		if (($subres = $this->rx('/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH|ENDS?\s+WITH)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'key');
		}
		else {
			$result = \array_pop($stack);
			$_27202 = \false; break;
		}
		$_27202 = \true; break;
	}
	while(\false);
	if($_27202 === \true) { return $this->finalise($result); }
	if($_27202 === \false) { return \false; }
}

public function op_keyword__finalise (&$result) {
        $key = preg_replace('#\s+#', '_', $result['key']['text']);
        $result['data'] = (isset($result['not']) ? 'NOT_' : '').match ($key) {
            'CONTAINS', 'CONTAIN' => 'CONTAINS',
            'MATCHES', 'MATCH' => 'MATCHES',
            'STARTS_WITH', 'START_WITH' => 'STARTS_WITH',
            'ENDS_WITH', 'END_WITH' => 'ENDS_WITH',
        };
        unset($result['not'], $result['key']);
    }

/* function_call: f:identifier > "(" > first:value_expression? (> "," > others:value_expression)* > ")" */
protected $match_function_call_typestack = ['function_call'];
function match_function_call($stack = []) {
	$matchrule = 'function_call';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27217 = \null;
	do {
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "f"); }
		else { $_27217 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_27217 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$res_27208 = $result;
		$pos_27208 = $this->pos;
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else {
			$result = $res_27208;
			$this->setPos($pos_27208);
			unset($res_27208, $pos_27208);
		}
		while (\true) {
			$res_27214 = $result;
			$pos_27214 = $this->pos;
			$_27213 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_27213 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_27213 = \false; break; }
				$_27213 = \true; break;
			}
			while(\false);
			if($_27213 === \false) {
				$result = $res_27214;
				$this->setPos($pos_27214);
				unset($res_27214, $pos_27214);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_27217 = \false; break; }
		$_27217 = \true; break;
	}
	while(\false);
	if($_27217 === \true) { return $this->finalise($result); }
	if($_27217 === \false) { return \false; }
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
	$_27233 = \null;
	do {
		$key = 'match_'.'value_or_expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27233 = \false; break; }
		while (\true) {
			$res_27232 = $result;
			$pos_27232 = $this->pos;
			$_27231 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_27227 = \null;
				do {
					$_27225 = \null;
					do {
						$res_27222 = $result;
						$pos_27222 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '/') {
							$this->addPos(1);
							$result["text"] .= '/';
							$_27225 = \true; break;
						}
						$result = $res_27222;
						$this->setPos($pos_27222);
						if (\substr($this->string, $this->pos, 1) === '*') {
							$this->addPos(1);
							$result["text"] .= '*';
							$_27225 = \true; break;
						}
						$result = $res_27222;
						$this->setPos($pos_27222);
						$_27225 = \false; break;
					}
					while(\false);
					if($_27225 === \false) { $_27227 = \false; break; }
					$_27227 = \true; break;
				}
				while(\false);
				if($_27227 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_27227 === \false) {
					$result = \array_pop($stack);
					$_27231 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_or_expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_27231 = \false; break; }
				$_27231 = \true; break;
			}
			while(\false);
			if($_27231 === \false) {
				$result = $res_27232;
				$this->setPos($pos_27232);
				unset($res_27232, $pos_27232);
				break;
			}
		}
		$_27233 = \true; break;
	}
	while(\false);
	if($_27233 === \true) { return $this->finalise($result); }
	if($_27233 === \false) { return \false; }
}

public function value_product_handleOperator (mixed $l, mixed $r, string $operator): array|int|float {
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
	$_27248 = \null;
	do {
		$key = 'match_'.'value_product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_27248 = \false; break; }
		while (\true) {
			$res_27247 = $result;
			$pos_27247 = $this->pos;
			$_27246 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_27242 = \null;
				do {
					$_27240 = \null;
					do {
						$res_27237 = $result;
						$pos_27237 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '+') {
							$this->addPos(1);
							$result["text"] .= '+';
							$_27240 = \true; break;
						}
						$result = $res_27237;
						$this->setPos($pos_27237);
						if (\substr($this->string, $this->pos, 1) === '-') {
							$this->addPos(1);
							$result["text"] .= '-';
							$_27240 = \true; break;
						}
						$result = $res_27237;
						$this->setPos($pos_27237);
						$_27240 = \false; break;
					}
					while(\false);
					if($_27240 === \false) { $_27242 = \false; break; }
					$_27242 = \true; break;
				}
				while(\false);
				if($_27242 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_27242 === \false) {
					$result = \array_pop($stack);
					$_27246 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_product'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_27246 = \false; break; }
				$_27246 = \true; break;
			}
			while(\false);
			if($_27246 === \false) {
				$result = $res_27247;
				$this->setPos($pos_27247);
				unset($res_27247, $pos_27247);
				break;
			}
		}
		$_27248 = \true; break;
	}
	while(\false);
	if($_27248 === \true) { return $this->finalise($result); }
	if($_27248 === \false) { return \false; }
}

public function value_sum_handleOperator (mixed $l, mixed $r, string $operator): array|int|float {
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

/* value_or_expr: v:value | ('(' > p:value_expression > ')') */
protected $match_value_or_expr_typestack = ['value_or_expr'];
function match_value_or_expr($stack = []) {
	$matchrule = 'value_or_expr';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27259 = \null;
	do {
		$res_27250 = $result;
		$pos_27250 = $this->pos;
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_27259 = \true; break;
		}
		$result = $res_27250;
		$this->setPos($pos_27250);
		$_27257 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_27257 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'value_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "p");
			}
			else { $_27257 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_27257 = \false; break; }
			$_27257 = \true; break;
		}
		while(\false);
		if($_27257 === \true) { $_27259 = \true; break; }
		$result = $res_27250;
		$this->setPos($pos_27250);
		$_27259 = \false; break;
	}
	while(\false);
	if($_27259 === \true) { return $this->finalise($result); }
	if($_27259 === \false) { return \false; }
}

public function value_or_expr__finalise (&$result) {
        if (isset($result['p'])) {
            $result['data'] = [
                'type' => 'parentheses',
                'expression' => $result['p']['data'],
            ];
            unset($result['p']);
            return;
        }
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

/* value: v:function_call | v:number | v:quoted_string | v:boolean | v:const_null | v:field */
protected $match_value_typestack = ['value'];
function match_value($stack = []) {
	$matchrule = 'value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27280 = \null;
	do {
		$res_27261 = $result;
		$pos_27261 = $this->pos;
		$key = 'match_'.'function_call'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_27280 = \true; break;
		}
		$result = $res_27261;
		$this->setPos($pos_27261);
		$_27278 = \null;
		do {
			$res_27263 = $result;
			$pos_27263 = $this->pos;
			$key = 'match_'.'number'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "v");
				$_27278 = \true; break;
			}
			$result = $res_27263;
			$this->setPos($pos_27263);
			$_27276 = \null;
			do {
				$res_27265 = $result;
				$pos_27265 = $this->pos;
				$key = 'match_'.'quoted_string'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_27276 = \true; break;
				}
				$result = $res_27265;
				$this->setPos($pos_27265);
				$_27274 = \null;
				do {
					$res_27267 = $result;
					$pos_27267 = $this->pos;
					$key = 'match_'.'boolean'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "v");
						$_27274 = \true; break;
					}
					$result = $res_27267;
					$this->setPos($pos_27267);
					$_27272 = \null;
					do {
						$res_27269 = $result;
						$pos_27269 = $this->pos;
						$key = 'match_'.'const_null'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "v");
							$_27272 = \true; break;
						}
						$result = $res_27269;
						$this->setPos($pos_27269);
						$key = 'match_'.'field'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "v");
							$_27272 = \true; break;
						}
						$result = $res_27269;
						$this->setPos($pos_27269);
						$_27272 = \false; break;
					}
					while(\false);
					if($_27272 === \true) { $_27274 = \true; break; }
					$result = $res_27267;
					$this->setPos($pos_27267);
					$_27274 = \false; break;
				}
				while(\false);
				if($_27274 === \true) { $_27276 = \true; break; }
				$result = $res_27265;
				$this->setPos($pos_27265);
				$_27276 = \false; break;
			}
			while(\false);
			if($_27276 === \true) { $_27278 = \true; break; }
			$result = $res_27263;
			$this->setPos($pos_27263);
			$_27278 = \false; break;
		}
		while(\false);
		if($_27278 === \true) { $_27280 = \true; break; }
		$result = $res_27261;
		$this->setPos($pos_27261);
		$_27280 = \false; break;
	}
	while(\false);
	if($_27280 === \true) { return $this->finalise($result); }
	if($_27280 === \false) { return \false; }
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
	$_27286 = \null;
	do {
		$res_27283 = $result;
		$pos_27283 = $this->pos;
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else {
			$result = $res_27283;
			$this->setPos($pos_27283);
			unset($res_27283, $pos_27283);
		}
		if (\substr($this->string, $this->pos, 1) === '.') {
			$this->addPos(1);
			$result["text"] .= '.';
		}
		else { $_27286 = \false; break; }
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_27286 = \false; break; }
		$_27286 = \true; break;
	}
	while(\false);
	if($_27286 === \true) { return $this->finalise($result); }
	if($_27286 === \false) { return \false; }
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

/* number: v:decimal | v:int */
protected $match_number_typestack = ['number'];
function match_number($stack = []) {
	$matchrule = 'number';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27292 = \null;
	do {
		$res_27289 = $result;
		$pos_27289 = $this->pos;
		$key = 'match_'.'decimal'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_27292 = \true; break;
		}
		$result = $res_27289;
		$this->setPos($pos_27289);
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_27292 = \true; break;
		}
		$result = $res_27289;
		$this->setPos($pos_27289);
		$_27292 = \false; break;
	}
	while(\false);
	if($_27292 === \true) { return $this->finalise($result); }
	if($_27292 === \false) { return \false; }
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
	$_27298 = \null;
	do {
		$key = 'match_'.'alpha'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_27298 = \false; break; }
		while (\true) {
			$res_27297 = $result;
			$pos_27297 = $this->pos;
			$key = 'match_'.'alphanum'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_27297;
				$this->setPos($pos_27297);
				unset($res_27297, $pos_27297);
				break;
			}
		}
		$_27298 = \true; break;
	}
	while(\false);
	if($_27298 === \true) { return $this->finalise($result); }
	if($_27298 === \false) { return \false; }
}



}
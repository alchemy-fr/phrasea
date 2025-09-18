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
	$_18298 = \null;
	do {
		$key = 'match_'.'and_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_18298 = \false; break; }
		while (\true) {
			$res_18297 = $result;
			$pos_18297 = $this->pos;
			$_18296 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_18296 = \false; break; }
				if (($subres = $this->literal('OR')) !== \false) { $result["text"] .= $subres; }
				else { $_18296 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_18296 = \false; break; }
				$key = 'match_'.'and_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_18296 = \false; break; }
				$_18296 = \true; break;
			}
			while(\false);
			if($_18296 === \false) {
				$result = $res_18297;
				$this->setPos($pos_18297);
				unset($res_18297, $pos_18297);
				break;
			}
		}
		$_18298 = \true; break;
	}
	while(\false);
	if($_18298 === \true) { return $this->finalise($result); }
	if($_18298 === \false) { return \false; }
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
	$_18307 = \null;
	do {
		$key = 'match_'.'condition'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_18307 = \false; break; }
		while (\true) {
			$res_18306 = $result;
			$pos_18306 = $this->pos;
			$_18305 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_18305 = \false; break; }
				if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
				else { $_18305 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_18305 = \false; break; }
				$key = 'match_'.'condition'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_18305 = \false; break; }
				$_18305 = \true; break;
			}
			while(\false);
			if($_18305 === \false) {
				$result = $res_18306;
				$this->setPos($pos_18306);
				unset($res_18306, $pos_18306);
				break;
			}
		}
		$_18307 = \true; break;
	}
	while(\false);
	if($_18307 === \true) { return $this->finalise($result); }
	if($_18307 === \false) { return \false; }
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
	$_18322 = \null;
	do {
		$res_18309 = $result;
		$pos_18309 = $this->pos;
		$_18315 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_18315 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
			}
			else { $_18315 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_18315 = \false; break; }
			$_18315 = \true; break;
		}
		while(\false);
		if($_18315 === \true) { $_18322 = \true; break; }
		$result = $res_18309;
		$this->setPos($pos_18309);
		$_18320 = \null;
		do {
			$res_18317 = $result;
			$pos_18317 = $this->pos;
			$key = 'match_'.'not_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_18320 = \true; break;
			}
			$result = $res_18317;
			$this->setPos($pos_18317);
			$key = 'match_'.'criteria'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "e");
				$_18320 = \true; break;
			}
			$result = $res_18317;
			$this->setPos($pos_18317);
			$_18320 = \false; break;
		}
		while(\false);
		if($_18320 === \true) { $_18322 = \true; break; }
		$result = $res_18309;
		$this->setPos($pos_18309);
		$_18322 = \false; break;
	}
	while(\false);
	if($_18322 === \true) { return $this->finalise($result); }
	if($_18322 === \false) { return \false; }
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
	$_18327 = \null;
	do {
		if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
		else { $_18327 = \false; break; }
		$key = 'match_'.'__'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_18327 = \false; break; }
		$key = 'match_'.'expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "e"); }
		else { $_18327 = \false; break; }
		$_18327 = \true; break;
	}
	while(\false);
	if($_18327 === \true) { return $this->finalise($result); }
	if($_18327 === \false) { return \false; }
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
	$_18331 = \null;
	do {
		$key = 'match_'.'field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "field");
		}
		else { $_18331 = \false; break; }
		$key = 'match_'.'operator'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_18331 = \false; break; }
		$_18331 = \true; break;
	}
	while(\false);
	if($_18331 === \true) { return $this->finalise($result); }
	if($_18331 === \false) { return \false; }
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
	$_18335 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '@') {
			$this->addPos(1);
			$result["text"] .= '@';
		}
		else { $_18335 = \false; break; }
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_18335 = \false; break; }
		$_18335 = \true; break;
	}
	while(\false);
	if($_18335 === \true) { return $this->finalise($result); }
	if($_18335 === \false) { return \false; }
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
	$_18341 = \null;
	do {
		$res_18338 = $result;
		$pos_18338 = $this->pos;
		$key = 'match_'.'builtin_field'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_18341 = \true; break;
		}
		$result = $res_18338;
		$this->setPos($pos_18338);
		$key = 'match_'.'field_name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "f");
			$_18341 = \true; break;
		}
		$result = $res_18338;
		$this->setPos($pos_18338);
		$_18341 = \false; break;
	}
	while(\false);
	if($_18341 === \true) { return $this->finalise($result); }
	if($_18341 === \false) { return \false; }
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
	$_18354 = \null;
	do {
		$res_18343 = $result;
		$pos_18343 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_18354 = \true; break;
		}
		$result = $res_18343;
		$this->setPos($pos_18343);
		$_18352 = \null;
		do {
			$res_18345 = $result;
			$pos_18345 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_18352 = \true; break;
			}
			$result = $res_18345;
			$this->setPos($pos_18345);
			$_18350 = \null;
			do {
				$res_18347 = $result;
				$pos_18347 = $this->pos;
				if (($subres = $this->literal('TRUE')) !== \false) {
					$result["text"] .= $subres;
					$_18350 = \true; break;
				}
				$result = $res_18347;
				$this->setPos($pos_18347);
				if (($subres = $this->literal('FALSE')) !== \false) {
					$result["text"] .= $subres;
					$_18350 = \true; break;
				}
				$result = $res_18347;
				$this->setPos($pos_18347);
				$_18350 = \false; break;
			}
			while(\false);
			if($_18350 === \true) { $_18352 = \true; break; }
			$result = $res_18345;
			$this->setPos($pos_18345);
			$_18352 = \false; break;
		}
		while(\false);
		if($_18352 === \true) { $_18354 = \true; break; }
		$result = $res_18343;
		$this->setPos($pos_18343);
		$_18354 = \false; break;
	}
	while(\false);
	if($_18354 === \true) { return $this->finalise($result); }
	if($_18354 === \false) { return \false; }
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
	$_18359 = \null;
	do {
		$res_18356 = $result;
		$pos_18356 = $this->pos;
		if (($subres = $this->literal('null')) !== \false) {
			$result["text"] .= $subres;
			$_18359 = \true; break;
		}
		$result = $res_18356;
		$this->setPos($pos_18356);
		if (($subres = $this->literal('NULL')) !== \false) {
			$result["text"] .= $subres;
			$_18359 = \true; break;
		}
		$result = $res_18356;
		$this->setPos($pos_18356);
		$_18359 = \false; break;
	}
	while(\false);
	if($_18359 === \true) { return $this->finalise($result); }
	if($_18359 === \false) { return \false; }
}

public function const_null__finalise (&$result) {
        $result['data'] = null;
    }

/* operator: ] op:between_operator | ] op:in_operator | ] op:geo_operator | ] op:ending_operator | > op:simple_operator | > op:keyword_operator */
protected $match_operator_typestack = ['operator'];
function match_operator($stack = []) {
	$matchrule = 'operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_18398 = \null;
	do {
		$res_18361 = $result;
		$pos_18361 = $this->pos;
		$_18364 = \null;
		do {
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_18364 = \false; break; }
			$key = 'match_'.'between_operator'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "op");
			}
			else { $_18364 = \false; break; }
			$_18364 = \true; break;
		}
		while(\false);
		if($_18364 === \true) { $_18398 = \true; break; }
		$result = $res_18361;
		$this->setPos($pos_18361);
		$_18396 = \null;
		do {
			$res_18366 = $result;
			$pos_18366 = $this->pos;
			$_18369 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				else { $_18369 = \false; break; }
				$key = 'match_'.'in_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "op");
				}
				else { $_18369 = \false; break; }
				$_18369 = \true; break;
			}
			while(\false);
			if($_18369 === \true) { $_18396 = \true; break; }
			$result = $res_18366;
			$this->setPos($pos_18366);
			$_18394 = \null;
			do {
				$res_18371 = $result;
				$pos_18371 = $this->pos;
				$_18374 = \null;
				do {
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					else { $_18374 = \false; break; }
					$key = 'match_'.'geo_operator'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "op");
					}
					else { $_18374 = \false; break; }
					$_18374 = \true; break;
				}
				while(\false);
				if($_18374 === \true) { $_18394 = \true; break; }
				$result = $res_18371;
				$this->setPos($pos_18371);
				$_18392 = \null;
				do {
					$res_18376 = $result;
					$pos_18376 = $this->pos;
					$_18379 = \null;
					do {
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						else { $_18379 = \false; break; }
						$key = 'match_'.'ending_operator'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "op");
						}
						else { $_18379 = \false; break; }
						$_18379 = \true; break;
					}
					while(\false);
					if($_18379 === \true) { $_18392 = \true; break; }
					$result = $res_18376;
					$this->setPos($pos_18376);
					$_18390 = \null;
					do {
						$res_18381 = $result;
						$pos_18381 = $this->pos;
						$_18384 = \null;
						do {
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$key = 'match_'.'simple_operator'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres, "op");
							}
							else { $_18384 = \false; break; }
							$_18384 = \true; break;
						}
						while(\false);
						if($_18384 === \true) { $_18390 = \true; break; }
						$result = $res_18381;
						$this->setPos($pos_18381);
						$_18388 = \null;
						do {
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$key = 'match_'.'keyword_operator'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres, "op");
							}
							else { $_18388 = \false; break; }
							$_18388 = \true; break;
						}
						while(\false);
						if($_18388 === \true) { $_18390 = \true; break; }
						$result = $res_18381;
						$this->setPos($pos_18381);
						$_18390 = \false; break;
					}
					while(\false);
					if($_18390 === \true) { $_18392 = \true; break; }
					$result = $res_18376;
					$this->setPos($pos_18376);
					$_18392 = \false; break;
				}
				while(\false);
				if($_18392 === \true) { $_18394 = \true; break; }
				$result = $res_18371;
				$this->setPos($pos_18371);
				$_18394 = \false; break;
			}
			while(\false);
			if($_18394 === \true) { $_18396 = \true; break; }
			$result = $res_18366;
			$this->setPos($pos_18366);
			$_18396 = \false; break;
		}
		while(\false);
		if($_18396 === \true) { $_18398 = \true; break; }
		$result = $res_18361;
		$this->setPos($pos_18361);
		$_18398 = \false; break;
	}
	while(\false);
	if($_18398 === \true) { return $this->finalise($result); }
	if($_18398 === \false) { return \false; }
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
	$_18409 = \null;
	do {
		if (($subres = $this->literal('WITHIN')) !== \false) { $result["text"] .= $subres; }
		else { $_18409 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_18409 = \false; break; }
		$_18407 = \null;
		do {
			$_18405 = \null;
			do {
				$res_18402 = $result;
				$pos_18402 = $this->pos;
				$key = 'match_'.'within_circle_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_18405 = \true; break;
				}
				$result = $res_18402;
				$this->setPos($pos_18402);
				$key = 'match_'.'within_rectangle_operator'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_18405 = \true; break;
				}
				$result = $res_18402;
				$this->setPos($pos_18402);
				$_18405 = \false; break;
			}
			while(\false);
			if($_18405 === \false) { $_18407 = \false; break; }
			$_18407 = \true; break;
		}
		while(\false);
		if($_18407 === \false) { $_18409 = \false; break; }
		$_18409 = \true; break;
	}
	while(\false);
	if($_18409 === \true) { return $this->finalise($result); }
	if($_18409 === \false) { return \false; }
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
	$_18426 = \null;
	do {
		if (($subres = $this->literal('CIRCLE')) !== \false) { $result["text"] .= $subres; }
		else { $_18426 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_18426 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "lat");
		}
		else { $_18426 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_18426 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "lng");
		}
		else { $_18426 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_18426 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "radius");
		}
		else { $_18426 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_18426 = \false; break; }
		$_18426 = \true; break;
	}
	while(\false);
	if($_18426 === \true) { return $this->finalise($result); }
	if($_18426 === \false) { return \false; }
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
	$_18447 = \null;
	do {
		if (($subres = $this->literal('RECTANGLE')) !== \false) { $result["text"] .= $subres; }
		else { $_18447 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_18447 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "topLeftLat");
		}
		else { $_18447 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_18447 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "topLeftLng");
		}
		else { $_18447 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_18447 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "bottomRightLat");
		}
		else { $_18447 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ',') {
			$this->addPos(1);
			$result["text"] .= ',';
		}
		else { $_18447 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "bottomRightLng");
		}
		else { $_18447 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_18447 = \false; break; }
		$_18447 = \true; break;
	}
	while(\false);
	if($_18447 === \true) { return $this->finalise($result); }
	if($_18447 === \false) { return \false; }
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
	$_18460 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_18452 = $result;
		$pos_18452 = $this->pos;
		$_18451 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_18451 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_18451 = \false; break; }
			$_18451 = \true; break;
		}
		while(\false);
		if($_18451 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_18451 === \false) {
			$result = $res_18452;
			$this->setPos($pos_18452);
			unset($res_18452, $pos_18452);
		}
		if (($subres = $this->literal('BETWEEN')) !== \false) { $result["text"] .= $subres; }
		else { $_18460 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_18460 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "left");
		}
		else { $_18460 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_18460 = \false; break; }
		if (($subres = $this->literal('AND')) !== \false) { $result["text"] .= $subres; }
		else { $_18460 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_18460 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "right");
		}
		else { $_18460 = \false; break; }
		$_18460 = \true; break;
	}
	while(\false);
	if($_18460 === \true) { return $this->finalise($result); }
	if($_18460 === \false) { return \false; }
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
	$_18469 = \null;
	do {
		$res_18462 = $result;
		$pos_18462 = $this->pos;
		$_18466 = \null;
		do {
			if (($subres = $this->literal('IS')) !== \false) { $result["text"] .= $subres; }
			else { $_18466 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_18466 = \false; break; }
			if (($subres = $this->literal('MISSING')) !== \false) { $result["text"] .= $subres; }
			else { $_18466 = \false; break; }
			$_18466 = \true; break;
		}
		while(\false);
		if($_18466 === \true) { $_18469 = \true; break; }
		$result = $res_18462;
		$this->setPos($pos_18462);
		if (($subres = $this->literal('EXISTS')) !== \false) {
			$result["text"] .= $subres;
			$_18469 = \true; break;
		}
		$result = $res_18462;
		$this->setPos($pos_18462);
		$_18469 = \false; break;
	}
	while(\false);
	if($_18469 === \true) { return $this->finalise($result); }
	if($_18469 === \false) { return \false; }
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

/* in_operator: not:("NOT" ] )? "IN" > '(' > first:value_expression (> ',' > others:value_expression)* > ')' */
protected $match_in_operator_typestack = ['in_operator'];
function match_in_operator($stack = []) {
	$matchrule = 'in_operator';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_18488 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_18474 = $result;
		$pos_18474 = $this->pos;
		$_18473 = \null;
		do {
			if (($subres = $this->literal('NOT')) !== \false) { $result["text"] .= $subres; }
			else { $_18473 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			else { $_18473 = \false; break; }
			$_18473 = \true; break;
		}
		while(\false);
		if($_18473 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		if($_18473 === \false) {
			$result = $res_18474;
			$this->setPos($pos_18474);
			unset($res_18474, $pos_18474);
		}
		if (($subres = $this->literal('IN')) !== \false) { $result["text"] .= $subres; }
		else { $_18488 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_18488 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else { $_18488 = \false; break; }
		while (\true) {
			$res_18485 = $result;
			$pos_18485 = $this->pos;
			$_18484 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_18484 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_18484 = \false; break; }
				$_18484 = \true; break;
			}
			while(\false);
			if($_18484 === \false) {
				$result = $res_18485;
				$this->setPos($pos_18485);
				unset($res_18485, $pos_18485);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_18488 = \false; break; }
		$_18488 = \true; break;
	}
	while(\false);
	if($_18488 === \true) { return $this->finalise($result); }
	if($_18488 === \false) { return \false; }
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
	$_18493 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		if (($subres = $this->rx('/([<>]?=|!=|[<>])/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		else {
			$result = \array_pop($stack);
			$_18493 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_18493 = \false; break; }
		$_18493 = \true; break;
	}
	while(\false);
	if($_18493 === \true) { return $this->finalise($result); }
	if($_18493 === \false) { return \false; }
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
	$_18498 = \null;
	do {
		$key = 'match_'.'op_keyword'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "op");
		}
		else { $_18498 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		else { $_18498 = \false; break; }
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_18498 = \false; break; }
		$_18498 = \true; break;
	}
	while(\false);
	if($_18498 === \true) { return $this->finalise($result); }
	if($_18498 === \false) { return \false; }
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
	$_18502 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "not");
		$res_18500 = $result;
		$pos_18500 = $this->pos;
		if (($subres = $this->rx('/(DO(ES)?\s+NOT\s+)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'not');
		}
		else {
			$result = $res_18500;
			$this->setPos($pos_18500);
			unset($res_18500, $pos_18500);
		}
		$stack[] = $result; $result = $this->construct($matchrule, "key");
		if (($subres = $this->rx('/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH)/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'key');
		}
		else {
			$result = \array_pop($stack);
			$_18502 = \false; break;
		}
		$_18502 = \true; break;
	}
	while(\false);
	if($_18502 === \true) { return $this->finalise($result); }
	if($_18502 === \false) { return \false; }
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
	$_18517 = \null;
	do {
		$key = 'match_'.'identifier'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "f"); }
		else { $_18517 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_18517 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$res_18508 = $result;
		$pos_18508 = $this->pos;
		$key = 'match_'.'value_expression'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "first");
		}
		else {
			$result = $res_18508;
			$this->setPos($pos_18508);
			unset($res_18508, $pos_18508);
		}
		while (\true) {
			$res_18514 = $result;
			$pos_18514 = $this->pos;
			$_18513 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else { $_18513 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_expression'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "others");
				}
				else { $_18513 = \false; break; }
				$_18513 = \true; break;
			}
			while(\false);
			if($_18513 === \false) {
				$result = $res_18514;
				$this->setPos($pos_18514);
				unset($res_18514, $pos_18514);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_18517 = \false; break; }
		$_18517 = \true; break;
	}
	while(\false);
	if($_18517 === \true) { return $this->finalise($result); }
	if($_18517 === \false) { return \false; }
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
	$_18533 = \null;
	do {
		$key = 'match_'.'value_or_expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_18533 = \false; break; }
		while (\true) {
			$res_18532 = $result;
			$pos_18532 = $this->pos;
			$_18531 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_18527 = \null;
				do {
					$_18525 = \null;
					do {
						$res_18522 = $result;
						$pos_18522 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '/') {
							$this->addPos(1);
							$result["text"] .= '/';
							$_18525 = \true; break;
						}
						$result = $res_18522;
						$this->setPos($pos_18522);
						if (\substr($this->string, $this->pos, 1) === '*') {
							$this->addPos(1);
							$result["text"] .= '*';
							$_18525 = \true; break;
						}
						$result = $res_18522;
						$this->setPos($pos_18522);
						$_18525 = \false; break;
					}
					while(\false);
					if($_18525 === \false) { $_18527 = \false; break; }
					$_18527 = \true; break;
				}
				while(\false);
				if($_18527 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_18527 === \false) {
					$result = \array_pop($stack);
					$_18531 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_or_expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_18531 = \false; break; }
				$_18531 = \true; break;
			}
			while(\false);
			if($_18531 === \false) {
				$result = $res_18532;
				$this->setPos($pos_18532);
				unset($res_18532, $pos_18532);
				break;
			}
		}
		$_18533 = \true; break;
	}
	while(\false);
	if($_18533 === \true) { return $this->finalise($result); }
	if($_18533 === \false) { return \false; }
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
	$_18548 = \null;
	do {
		$key = 'match_'.'value_product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres, "v"); }
		else { $_18548 = \false; break; }
		while (\true) {
			$res_18547 = $result;
			$pos_18547 = $this->pos;
			$_18546 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$stack[] = $result; $result = $this->construct($matchrule, "sign");
				$_18542 = \null;
				do {
					$_18540 = \null;
					do {
						$res_18537 = $result;
						$pos_18537 = $this->pos;
						if (\substr($this->string, $this->pos, 1) === '+') {
							$this->addPos(1);
							$result["text"] .= '+';
							$_18540 = \true; break;
						}
						$result = $res_18537;
						$this->setPos($pos_18537);
						if (\substr($this->string, $this->pos, 1) === '-') {
							$this->addPos(1);
							$result["text"] .= '-';
							$_18540 = \true; break;
						}
						$result = $res_18537;
						$this->setPos($pos_18537);
						$_18540 = \false; break;
					}
					while(\false);
					if($_18540 === \false) { $_18542 = \false; break; }
					$_18542 = \true; break;
				}
				while(\false);
				if($_18542 === \true) {
					$subres = $result; $result = \array_pop($stack);
					$this->store($result, $subres, 'sign');
				}
				if($_18542 === \false) {
					$result = \array_pop($stack);
					$_18546 = \false; break;
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'value_product'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "right");
				}
				else { $_18546 = \false; break; }
				$_18546 = \true; break;
			}
			while(\false);
			if($_18546 === \false) {
				$result = $res_18547;
				$this->setPos($pos_18547);
				unset($res_18547, $pos_18547);
				break;
			}
		}
		$_18548 = \true; break;
	}
	while(\false);
	if($_18548 === \true) { return $this->finalise($result); }
	if($_18548 === \false) { return \false; }
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
	$_18559 = \null;
	do {
		$res_18550 = $result;
		$pos_18550 = $this->pos;
		$key = 'match_'.'value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_18559 = \true; break;
		}
		$result = $res_18550;
		$this->setPos($pos_18550);
		$_18557 = \null;
		do {
			if (\substr($this->string, $this->pos, 1) === '(') {
				$this->addPos(1);
				$result["text"] .= '(';
			}
			else { $_18557 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$key = 'match_'.'value_expression'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "p");
			}
			else { $_18557 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			if (\substr($this->string, $this->pos, 1) === ')') {
				$this->addPos(1);
				$result["text"] .= ')';
			}
			else { $_18557 = \false; break; }
			$_18557 = \true; break;
		}
		while(\false);
		if($_18557 === \true) { $_18559 = \true; break; }
		$result = $res_18550;
		$this->setPos($pos_18550);
		$_18559 = \false; break;
	}
	while(\false);
	if($_18559 === \true) { return $this->finalise($result); }
	if($_18559 === \false) { return \false; }
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
	$_18580 = \null;
	do {
		$res_18561 = $result;
		$pos_18561 = $this->pos;
		$key = 'match_'.'function_call'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_18580 = \true; break;
		}
		$result = $res_18561;
		$this->setPos($pos_18561);
		$_18578 = \null;
		do {
			$res_18563 = $result;
			$pos_18563 = $this->pos;
			$key = 'match_'.'number'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres, "v");
				$_18578 = \true; break;
			}
			$result = $res_18563;
			$this->setPos($pos_18563);
			$_18576 = \null;
			do {
				$res_18565 = $result;
				$pos_18565 = $this->pos;
				$key = 'match_'.'quoted_string'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres, "v");
					$_18576 = \true; break;
				}
				$result = $res_18565;
				$this->setPos($pos_18565);
				$_18574 = \null;
				do {
					$res_18567 = $result;
					$pos_18567 = $this->pos;
					$key = 'match_'.'boolean'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres, "v");
						$_18574 = \true; break;
					}
					$result = $res_18567;
					$this->setPos($pos_18567);
					$_18572 = \null;
					do {
						$res_18569 = $result;
						$pos_18569 = $this->pos;
						$key = 'match_'.'const_null'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "v");
							$_18572 = \true; break;
						}
						$result = $res_18569;
						$this->setPos($pos_18569);
						$key = 'match_'.'field'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres, "v");
							$_18572 = \true; break;
						}
						$result = $res_18569;
						$this->setPos($pos_18569);
						$_18572 = \false; break;
					}
					while(\false);
					if($_18572 === \true) { $_18574 = \true; break; }
					$result = $res_18567;
					$this->setPos($pos_18567);
					$_18574 = \false; break;
				}
				while(\false);
				if($_18574 === \true) { $_18576 = \true; break; }
				$result = $res_18565;
				$this->setPos($pos_18565);
				$_18576 = \false; break;
			}
			while(\false);
			if($_18576 === \true) { $_18578 = \true; break; }
			$result = $res_18563;
			$this->setPos($pos_18563);
			$_18578 = \false; break;
		}
		while(\false);
		if($_18578 === \true) { $_18580 = \true; break; }
		$result = $res_18561;
		$this->setPos($pos_18561);
		$_18580 = \false; break;
	}
	while(\false);
	if($_18580 === \true) { return $this->finalise($result); }
	if($_18580 === \false) { return \false; }
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
	$_18586 = \null;
	do {
		$res_18583 = $result;
		$pos_18583 = $this->pos;
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else {
			$result = $res_18583;
			$this->setPos($pos_18583);
			unset($res_18583, $pos_18583);
		}
		if (\substr($this->string, $this->pos, 1) === '.') {
			$this->addPos(1);
			$result["text"] .= '.';
		}
		else { $_18586 = \false; break; }
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_18586 = \false; break; }
		$_18586 = \true; break;
	}
	while(\false);
	if($_18586 === \true) { return $this->finalise($result); }
	if($_18586 === \false) { return \false; }
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
	$_18592 = \null;
	do {
		$res_18589 = $result;
		$pos_18589 = $this->pos;
		$key = 'match_'.'decimal'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_18592 = \true; break;
		}
		$result = $res_18589;
		$this->setPos($pos_18589);
		$key = 'match_'.'int'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "v");
			$_18592 = \true; break;
		}
		$result = $res_18589;
		$this->setPos($pos_18589);
		$_18592 = \false; break;
	}
	while(\false);
	if($_18592 === \true) { return $this->finalise($result); }
	if($_18592 === \false) { return \false; }
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
	$_18598 = \null;
	do {
		$key = 'match_'.'alpha'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_18598 = \false; break; }
		while (\true) {
			$res_18597 = $result;
			$pos_18597 = $this->pos;
			$key = 'match_'.'alphanum'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_18597;
				$this->setPos($pos_18597);
				unset($res_18597, $pos_18597);
				break;
			}
		}
		$_18598 = \true; break;
	}
	while(\false);
	if($_18598 === \true) { return $this->finalise($result); }
	if($_18598 === \false) { return \false; }
}



}
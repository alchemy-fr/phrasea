<?php

namespace App\Elasticsearch\AQL;

use hafriedlander\Peg\Parser;

class AQLGrammar extends Parser\Basic
{
    /* main: e:expression */
    protected $match_main_typestack = ['main'];

    public function match_main($stack = [])
    {
        $matchrule = 'main';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $key = 'match_expression';
        $pos = $this->pos;
        $subres = $this->packhas($key, $pos)
            ? $this->packread($key, $pos)
            : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
        if (\false !== $subres) {
            $this->store($result, $subres, 'e');

            return $this->finalise($result);
        } else {
            return \false;
        }
    }

    public function main__finalise(&$result)
    {
        $result['data'] = $result['e']['data'];
        unset($result['e']);
    }

    /* expression: left:and_expression (] "OR" ] right:and_expression ) * */
    protected $match_expression_typestack = ['expression'];

    public function match_expression($stack = [])
    {
        $matchrule = 'expression';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11344 = \null;
        do {
            $key = 'match_and_expression';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'left');
            } else {
                $_11344 = \false;
                break;
            }
            while (\true) {
                $res_11343 = $result;
                $pos_11343 = $this->pos;
                $_11342 = \null;
                do {
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    } else {
                        $_11342 = \false;
                        break;
                    }
                    if (($subres = $this->literal('OR')) !== \false) {
                        $result['text'] .= $subres;
                    } else {
                        $_11342 = \false;
                        break;
                    }
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    } else {
                        $_11342 = \false;
                        break;
                    }
                    $key = 'match_and_expression';
                    $pos = $this->pos;
                    $subres = $this->packhas($key, $pos)
                        ? $this->packread($key, $pos)
                        : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                    if (\false !== $subres) {
                        $this->store($result, $subres, 'right');
                    } else {
                        $_11342 = \false;
                        break;
                    }
                    $_11342 = \true;
                    break;
                } while (\false);
                if (\false === $_11342) {
                    $result = $res_11343;
                    $this->setPos($pos_11343);
                    unset($res_11343, $pos_11343);
                    break;
                }
            }
            $_11344 = \true;
            break;
        } while (\false);
        if (\true === $_11344) {
            return $this->finalise($result);
        }
        if (\false === $_11344) {
            return \false;
        }
    }

    public function expression__finalise(&$result)
    {
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
        if (1 === count($conditions)) {
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

    public function match_and_expression($stack = [])
    {
        $matchrule = 'and_expression';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11353 = \null;
        do {
            $key = 'match_condition';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'left');
            } else {
                $_11353 = \false;
                break;
            }
            while (\true) {
                $res_11352 = $result;
                $pos_11352 = $this->pos;
                $_11351 = \null;
                do {
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    } else {
                        $_11351 = \false;
                        break;
                    }
                    if (($subres = $this->literal('AND')) !== \false) {
                        $result['text'] .= $subres;
                    } else {
                        $_11351 = \false;
                        break;
                    }
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    } else {
                        $_11351 = \false;
                        break;
                    }
                    $key = 'match_condition';
                    $pos = $this->pos;
                    $subres = $this->packhas($key, $pos)
                        ? $this->packread($key, $pos)
                        : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                    if (\false !== $subres) {
                        $this->store($result, $subres, 'right');
                    } else {
                        $_11351 = \false;
                        break;
                    }
                    $_11351 = \true;
                    break;
                } while (\false);
                if (\false === $_11351) {
                    $result = $res_11352;
                    $this->setPos($pos_11352);
                    unset($res_11352, $pos_11352);
                    break;
                }
            }
            $_11353 = \true;
            break;
        } while (\false);
        if (\true === $_11353) {
            return $this->finalise($result);
        }
        if (\false === $_11353) {
            return \false;
        }
    }

    public function and_expression__finalise(&$result)
    {
        $conditions = [$result['left']['data']];
        if (isset($result['right']['_matchrule'])) {
            $conditions[] = $result['right']['data'];
        } else {
            foreach ($result['right'] ?? [] as $right) {
                $conditions[] = $right['data'];
            }
        }
        unset($result['left'], $result['right']);
        if (1 === count($conditions)) {
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

    public function match_condition($stack = [])
    {
        $matchrule = 'condition';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11368 = \null;
        do {
            $res_11355 = $result;
            $pos_11355 = $this->pos;
            $_11361 = \null;
            do {
                if ('(' === \substr($this->string, $this->pos, 1)) {
                    $this->addPos(1);
                    $result['text'] .= '(';
                } else {
                    $_11361 = \false;
                    break;
                }
                if (($subres = $this->whitespace()) !== \false) {
                    $result['text'] .= $subres;
                }
                $key = 'match_expression';
                $pos = $this->pos;
                $subres = $this->packhas($key, $pos)
                    ? $this->packread($key, $pos)
                    : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                if (\false !== $subres) {
                    $this->store($result, $subres, 'e');
                } else {
                    $_11361 = \false;
                    break;
                }
                if (($subres = $this->whitespace()) !== \false) {
                    $result['text'] .= $subres;
                }
                if (')' === \substr($this->string, $this->pos, 1)) {
                    $this->addPos(1);
                    $result['text'] .= ')';
                } else {
                    $_11361 = \false;
                    break;
                }
                $_11361 = \true;
                break;
            } while (\false);
            if (\true === $_11361) {
                $_11368 = \true;
                break;
            }
            $result = $res_11355;
            $this->setPos($pos_11355);
            $_11366 = \null;
            do {
                $res_11363 = $result;
                $pos_11363 = $this->pos;
                $key = 'match_not_expression';
                $pos = $this->pos;
                $subres = $this->packhas($key, $pos)
                    ? $this->packread($key, $pos)
                    : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                if (\false !== $subres) {
                    $this->store($result, $subres, 'e');
                    $_11366 = \true;
                    break;
                }
                $result = $res_11363;
                $this->setPos($pos_11363);
                $key = 'match_criteria';
                $pos = $this->pos;
                $subres = $this->packhas($key, $pos)
                    ? $this->packread($key, $pos)
                    : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                if (\false !== $subres) {
                    $this->store($result, $subres, 'e');
                    $_11366 = \true;
                    break;
                }
                $result = $res_11363;
                $this->setPos($pos_11363);
                $_11366 = \false;
                break;
            } while (\false);
            if (\true === $_11366) {
                $_11368 = \true;
                break;
            }
            $result = $res_11355;
            $this->setPos($pos_11355);
            $_11368 = \false;
            break;
        } while (\false);
        if (\true === $_11368) {
            return $this->finalise($result);
        }
        if (\false === $_11368) {
            return \false;
        }
    }

    public function condition__finalise(&$result)
    {
        $result['data'] = $result['e']['data'];
        unset($result['e']);
    }

    /* not_expression: "NOT" __ e:expression */
    protected $match_not_expression_typestack = ['not_expression'];

    public function match_not_expression($stack = [])
    {
        $matchrule = 'not_expression';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11373 = \null;
        do {
            if (($subres = $this->literal('NOT')) !== \false) {
                $result['text'] .= $subres;
            } else {
                $_11373 = \false;
                break;
            }
            $key = 'match___';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres);
            } else {
                $_11373 = \false;
                break;
            }
            $key = 'match_expression';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'e');
            } else {
                $_11373 = \false;
                break;
            }
            $_11373 = \true;
            break;
        } while (\false);
        if (\true === $_11373) {
            return $this->finalise($result);
        }
        if (\false === $_11373) {
            return \false;
        }
    }

    public function not_expression__finalise(&$result)
    {
        $result['data'] = [
            'type' => 'expression',
            'operator' => 'NOT',
            'conditions' => [$result['e']['data']],
        ];
        unset($result['e']);
    }

    /* criteria: field:field op:operator */
    protected $match_criteria_typestack = ['criteria'];

    public function match_criteria($stack = [])
    {
        $matchrule = 'criteria';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11377 = \null;
        do {
            $key = 'match_field';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'field');
            } else {
                $_11377 = \false;
                break;
            }
            $key = 'match_operator';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'op');
            } else {
                $_11377 = \false;
                break;
            }
            $_11377 = \true;
            break;
        } while (\false);
        if (\true === $_11377) {
            return $this->finalise($result);
        }
        if (\false === $_11377) {
            return \false;
        }
    }

    public function criteria__finalise(&$result)
    {
        $result['data'] = [
            'type' => 'criteria',
            'leftOperand' => $result['field']['data'],
            ...$result['op']['data'],
        ];
        unset($result['field'], $result['op']);
    }

    /* builtin_field: "@" keyword */
    protected $match_builtin_field_typestack = ['builtin_field'];

    public function match_builtin_field($stack = [])
    {
        $matchrule = 'builtin_field';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11381 = \null;
        do {
            if ('@' === \substr($this->string, $this->pos, 1)) {
                $this->addPos(1);
                $result['text'] .= '@';
            } else {
                $_11381 = \false;
                break;
            }
            $key = 'match_keyword';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres);
            } else {
                $_11381 = \false;
                break;
            }
            $_11381 = \true;
            break;
        } while (\false);
        if (\true === $_11381) {
            return $this->finalise($result);
        }
        if (\false === $_11381) {
            return \false;
        }
    }

    public function builtin_field__finalise(&$result)
    {
        $result['data'] = ['field' => $result['text']];
    }

    /* field_name: keyword */
    protected $match_field_name_typestack = ['field_name'];

    public function match_field_name($stack = [])
    {
        $matchrule = 'field_name';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $key = 'match_keyword';
        $pos = $this->pos;
        $subres = $this->packhas($key, $pos)
            ? $this->packread($key, $pos)
            : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
        if (\false !== $subres) {
            $this->store($result, $subres);

            return $this->finalise($result);
        } else {
            return \false;
        }
    }

    public function field_name__finalise(&$result)
    {
        $result['data'] = ['field' => $result['text']];
    }

    /* field: f:builtin_field | f:field_name */
    protected $match_field_typestack = ['field'];

    public function match_field($stack = [])
    {
        $matchrule = 'field';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11387 = \null;
        do {
            $res_11384 = $result;
            $pos_11384 = $this->pos;
            $key = 'match_builtin_field';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'f');
                $_11387 = \true;
                break;
            }
            $result = $res_11384;
            $this->setPos($pos_11384);
            $key = 'match_field_name';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'f');
                $_11387 = \true;
                break;
            }
            $result = $res_11384;
            $this->setPos($pos_11384);
            $_11387 = \false;
            break;
        } while (\false);
        if (\true === $_11387) {
            return $this->finalise($result);
        }
        if (\false === $_11387) {
            return \false;
        }
    }

    public function field__finalise(&$result)
    {
        $result['data'] = $result['f']['data'];
        unset($result['f']);
    }

    /* boolean: "true" | "false" */
    protected $match_boolean_typestack = ['boolean'];

    public function match_boolean($stack = [])
    {
        $matchrule = 'boolean';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11392 = \null;
        do {
            $res_11389 = $result;
            $pos_11389 = $this->pos;
            if (($subres = $this->literal('true')) !== \false) {
                $result['text'] .= $subres;
                $_11392 = \true;
                break;
            }
            $result = $res_11389;
            $this->setPos($pos_11389);
            if (($subres = $this->literal('false')) !== \false) {
                $result['text'] .= $subres;
                $_11392 = \true;
                break;
            }
            $result = $res_11389;
            $this->setPos($pos_11389);
            $_11392 = \false;
            break;
        } while (\false);
        if (\true === $_11392) {
            return $this->finalise($result);
        }
        if (\false === $_11392) {
            return \false;
        }
    }

    public function boolean__finalise(&$result)
    {
        $result['data'] = 'true' === $result['text'];
    }

    /* operator: ] op:between_operator | ] op:in_operator | ] op:ending_operator | > op:simple_operator | > op:keyword_operator */
    protected $match_operator_typestack = ['operator'];

    public function match_operator($stack = [])
    {
        $matchrule = 'operator';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11424 = \null;
        do {
            $res_11394 = $result;
            $pos_11394 = $this->pos;
            $_11397 = \null;
            do {
                if (($subres = $this->whitespace()) !== \false) {
                    $result['text'] .= $subres;
                } else {
                    $_11397 = \false;
                    break;
                }
                $key = 'match_between_operator';
                $pos = $this->pos;
                $subres = $this->packhas($key, $pos)
                    ? $this->packread($key, $pos)
                    : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                if (\false !== $subres) {
                    $this->store($result, $subres, 'op');
                } else {
                    $_11397 = \false;
                    break;
                }
                $_11397 = \true;
                break;
            } while (\false);
            if (\true === $_11397) {
                $_11424 = \true;
                break;
            }
            $result = $res_11394;
            $this->setPos($pos_11394);
            $_11422 = \null;
            do {
                $res_11399 = $result;
                $pos_11399 = $this->pos;
                $_11402 = \null;
                do {
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    } else {
                        $_11402 = \false;
                        break;
                    }
                    $key = 'match_in_operator';
                    $pos = $this->pos;
                    $subres = $this->packhas($key, $pos)
                        ? $this->packread($key, $pos)
                        : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                    if (\false !== $subres) {
                        $this->store($result, $subres, 'op');
                    } else {
                        $_11402 = \false;
                        break;
                    }
                    $_11402 = \true;
                    break;
                } while (\false);
                if (\true === $_11402) {
                    $_11422 = \true;
                    break;
                }
                $result = $res_11399;
                $this->setPos($pos_11399);
                $_11420 = \null;
                do {
                    $res_11404 = $result;
                    $pos_11404 = $this->pos;
                    $_11407 = \null;
                    do {
                        if (($subres = $this->whitespace()) !== \false) {
                            $result['text'] .= $subres;
                        } else {
                            $_11407 = \false;
                            break;
                        }
                        $key = 'match_ending_operator';
                        $pos = $this->pos;
                        $subres = $this->packhas($key, $pos)
                            ? $this->packread($key, $pos)
                            : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                        if (\false !== $subres) {
                            $this->store($result, $subres, 'op');
                        } else {
                            $_11407 = \false;
                            break;
                        }
                        $_11407 = \true;
                        break;
                    } while (\false);
                    if (\true === $_11407) {
                        $_11420 = \true;
                        break;
                    }
                    $result = $res_11404;
                    $this->setPos($pos_11404);
                    $_11418 = \null;
                    do {
                        $res_11409 = $result;
                        $pos_11409 = $this->pos;
                        $_11412 = \null;
                        do {
                            if (($subres = $this->whitespace()) !== \false) {
                                $result['text'] .= $subres;
                            }
                            $key = 'match_simple_operator';
                            $pos = $this->pos;
                            $subres = $this->packhas($key, $pos)
                                ? $this->packread($key, $pos)
                                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                            if (\false !== $subres) {
                                $this->store($result, $subres, 'op');
                            } else {
                                $_11412 = \false;
                                break;
                            }
                            $_11412 = \true;
                            break;
                        } while (\false);
                        if (\true === $_11412) {
                            $_11418 = \true;
                            break;
                        }
                        $result = $res_11409;
                        $this->setPos($pos_11409);
                        $_11416 = \null;
                        do {
                            if (($subres = $this->whitespace()) !== \false) {
                                $result['text'] .= $subres;
                            }
                            $key = 'match_keyword_operator';
                            $pos = $this->pos;
                            $subres = $this->packhas($key, $pos)
                                ? $this->packread($key, $pos)
                                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                            if (\false !== $subres) {
                                $this->store($result, $subres, 'op');
                            } else {
                                $_11416 = \false;
                                break;
                            }
                            $_11416 = \true;
                            break;
                        } while (\false);
                        if (\true === $_11416) {
                            $_11418 = \true;
                            break;
                        }
                        $result = $res_11409;
                        $this->setPos($pos_11409);
                        $_11418 = \false;
                        break;
                    } while (\false);
                    if (\true === $_11418) {
                        $_11420 = \true;
                        break;
                    }
                    $result = $res_11404;
                    $this->setPos($pos_11404);
                    $_11420 = \false;
                    break;
                } while (\false);
                if (\true === $_11420) {
                    $_11422 = \true;
                    break;
                }
                $result = $res_11399;
                $this->setPos($pos_11399);
                $_11422 = \false;
                break;
            } while (\false);
            if (\true === $_11422) {
                $_11424 = \true;
                break;
            }
            $result = $res_11394;
            $this->setPos($pos_11394);
            $_11424 = \false;
            break;
        } while (\false);
        if (\true === $_11424) {
            return $this->finalise($result);
        }
        if (\false === $_11424) {
            return \false;
        }
    }

    public function operator__finalise(&$result)
    {
        $result['data'] = $result['op']['data'];
        unset($result['op']);
    }

    /* between_operator: not:("NOT" ])? "BETWEEN" ] left:value_expression ] "AND" ] right:value_expression */
    protected $match_between_operator_typestack = ['between_operator'];

    public function match_between_operator($stack = [])
    {
        $matchrule = 'between_operator';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11437 = \null;
        do {
            $stack[] = $result;
            $result = $this->construct($matchrule, 'not');
            $res_11429 = $result;
            $pos_11429 = $this->pos;
            $_11428 = \null;
            do {
                if (($subres = $this->literal('NOT')) !== \false) {
                    $result['text'] .= $subres;
                } else {
                    $_11428 = \false;
                    break;
                }
                if (($subres = $this->whitespace()) !== \false) {
                    $result['text'] .= $subres;
                } else {
                    $_11428 = \false;
                    break;
                }
                $_11428 = \true;
                break;
            } while (\false);
            if (\true === $_11428) {
                $subres = $result;
                $result = \array_pop($stack);
                $this->store($result, $subres, 'not');
            }
            if (\false === $_11428) {
                $result = $res_11429;
                $this->setPos($pos_11429);
                unset($res_11429, $pos_11429);
            }
            if (($subres = $this->literal('BETWEEN')) !== \false) {
                $result['text'] .= $subres;
            } else {
                $_11437 = \false;
                break;
            }
            if (($subres = $this->whitespace()) !== \false) {
                $result['text'] .= $subres;
            } else {
                $_11437 = \false;
                break;
            }
            $key = 'match_value_expression';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'left');
            } else {
                $_11437 = \false;
                break;
            }
            if (($subres = $this->whitespace()) !== \false) {
                $result['text'] .= $subres;
            } else {
                $_11437 = \false;
                break;
            }
            if (($subres = $this->literal('AND')) !== \false) {
                $result['text'] .= $subres;
            } else {
                $_11437 = \false;
                break;
            }
            if (($subres = $this->whitespace()) !== \false) {
                $result['text'] .= $subres;
            } else {
                $_11437 = \false;
                break;
            }
            $key = 'match_value_expression';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'right');
            } else {
                $_11437 = \false;
                break;
            }
            $_11437 = \true;
            break;
        } while (\false);
        if (\true === $_11437) {
            return $this->finalise($result);
        }
        if (\false === $_11437) {
            return \false;
        }
    }

    public function between_operator__finalise(&$result)
    {
        $result['data'] = [
            'operator' => isset($result['not']) ? 'NOT_BETWEEN' : 'BETWEEN',
            'rightOperand' => [$result['left']['data'], $result['right']['data']],
        ];
        unset($result['left'], $result['right']);
    }

    /* ending_operator: ("IS" ] "MISSING") | "EXISTS" */
    protected $match_ending_operator_typestack = ['ending_operator'];

    public function match_ending_operator($stack = [])
    {
        $matchrule = 'ending_operator';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11446 = \null;
        do {
            $res_11439 = $result;
            $pos_11439 = $this->pos;
            $_11443 = \null;
            do {
                if (($subres = $this->literal('IS')) !== \false) {
                    $result['text'] .= $subres;
                } else {
                    $_11443 = \false;
                    break;
                }
                if (($subres = $this->whitespace()) !== \false) {
                    $result['text'] .= $subres;
                } else {
                    $_11443 = \false;
                    break;
                }
                if (($subres = $this->literal('MISSING')) !== \false) {
                    $result['text'] .= $subres;
                } else {
                    $_11443 = \false;
                    break;
                }
                $_11443 = \true;
                break;
            } while (\false);
            if (\true === $_11443) {
                $_11446 = \true;
                break;
            }
            $result = $res_11439;
            $this->setPos($pos_11439);
            if (($subres = $this->literal('EXISTS')) !== \false) {
                $result['text'] .= $subres;
                $_11446 = \true;
                break;
            }
            $result = $res_11439;
            $this->setPos($pos_11439);
            $_11446 = \false;
            break;
        } while (\false);
        if (\true === $_11446) {
            return $this->finalise($result);
        }
        if (\false === $_11446) {
            return \false;
        }
    }

    public function ending_operator__finalise(&$result)
    {
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

    public function match_in_operator($stack = [])
    {
        $matchrule = 'in_operator';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11465 = \null;
        do {
            $stack[] = $result;
            $result = $this->construct($matchrule, 'not');
            $res_11451 = $result;
            $pos_11451 = $this->pos;
            $_11450 = \null;
            do {
                if (($subres = $this->literal('NOT')) !== \false) {
                    $result['text'] .= $subres;
                } else {
                    $_11450 = \false;
                    break;
                }
                if (($subres = $this->whitespace()) !== \false) {
                    $result['text'] .= $subres;
                } else {
                    $_11450 = \false;
                    break;
                }
                $_11450 = \true;
                break;
            } while (\false);
            if (\true === $_11450) {
                $subres = $result;
                $result = \array_pop($stack);
                $this->store($result, $subres, 'not');
            }
            if (\false === $_11450) {
                $result = $res_11451;
                $this->setPos($pos_11451);
                unset($res_11451, $pos_11451);
            }
            if (($subres = $this->literal('IN')) !== \false) {
                $result['text'] .= $subres;
            } else {
                $_11465 = \false;
                break;
            }
            if (($subres = $this->whitespace()) !== \false) {
                $result['text'] .= $subres;
            }
            if ('(' === \substr($this->string, $this->pos, 1)) {
                $this->addPos(1);
                $result['text'] .= '(';
            } else {
                $_11465 = \false;
                break;
            }
            if (($subres = $this->whitespace()) !== \false) {
                $result['text'] .= $subres;
            }
            $key = 'match_value_expression';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'first');
            } else {
                $_11465 = \false;
                break;
            }
            while (\true) {
                $res_11462 = $result;
                $pos_11462 = $this->pos;
                $_11461 = \null;
                do {
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    }
                    if (',' === \substr($this->string, $this->pos, 1)) {
                        $this->addPos(1);
                        $result['text'] .= ',';
                    } else {
                        $_11461 = \false;
                        break;
                    }
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    }
                    $key = 'match_value_expression';
                    $pos = $this->pos;
                    $subres = $this->packhas($key, $pos)
                        ? $this->packread($key, $pos)
                        : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                    if (\false !== $subres) {
                        $this->store($result, $subres, 'others');
                    } else {
                        $_11461 = \false;
                        break;
                    }
                    $_11461 = \true;
                    break;
                } while (\false);
                if (\false === $_11461) {
                    $result = $res_11462;
                    $this->setPos($pos_11462);
                    unset($res_11462, $pos_11462);
                    break;
                }
            }
            if (($subres = $this->whitespace()) !== \false) {
                $result['text'] .= $subres;
            }
            if (')' === \substr($this->string, $this->pos, 1)) {
                $this->addPos(1);
                $result['text'] .= ')';
            } else {
                $_11465 = \false;
                break;
            }
            $_11465 = \true;
            break;
        } while (\false);
        if (\true === $_11465) {
            return $this->finalise($result);
        }
        if (\false === $_11465) {
            return \false;
        }
    }

    public function in_operator__finalise(&$result)
    {
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

    public function match_simple_operator($stack = [])
    {
        $matchrule = 'simple_operator';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11470 = \null;
        do {
            $stack[] = $result;
            $result = $this->construct($matchrule, 'op');
            if (($subres = $this->rx('/([<>]?=|!=|[<>])/')) !== \false) {
                $result['text'] .= $subres;
                $subres = $result;
                $result = \array_pop($stack);
                $this->store($result, $subres, 'op');
            } else {
                $result = \array_pop($stack);
                $_11470 = \false;
                break;
            }
            if (($subres = $this->whitespace()) !== \false) {
                $result['text'] .= $subres;
            }
            $key = 'match_value_expression';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'v');
            } else {
                $_11470 = \false;
                break;
            }
            $_11470 = \true;
            break;
        } while (\false);
        if (\true === $_11470) {
            return $this->finalise($result);
        }
        if (\false === $_11470) {
            return \false;
        }
    }

    public function simple_operator__finalise(&$result)
    {
        $result['data'] = [
            'operator' => preg_replace('#\s+#', '_', $result['op']['text']),
            'rightOperand' => $result['v']['data'],
        ];
        unset($result['op'], $result['v']);
    }

    /* keyword_operator: op:op_keyword ] v:value_expression */
    protected $match_keyword_operator_typestack = ['keyword_operator'];

    public function match_keyword_operator($stack = [])
    {
        $matchrule = 'keyword_operator';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11475 = \null;
        do {
            $key = 'match_op_keyword';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'op');
            } else {
                $_11475 = \false;
                break;
            }
            if (($subres = $this->whitespace()) !== \false) {
                $result['text'] .= $subres;
            } else {
                $_11475 = \false;
                break;
            }
            $key = 'match_value_expression';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'v');
            } else {
                $_11475 = \false;
                break;
            }
            $_11475 = \true;
            break;
        } while (\false);
        if (\true === $_11475) {
            return $this->finalise($result);
        }
        if (\false === $_11475) {
            return \false;
        }
    }

    public function keyword_operator__finalise(&$result)
    {
        $result['data'] = [
            'operator' => $result['op']['data'],
            'rightOperand' => $result['v']['data'],
        ];
        unset($result['op'], $result['v']);
    }

    /* op_keyword: not:/(DO(ES)?\s+NOT\s+)/? key:/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH)/ */
    protected $match_op_keyword_typestack = ['op_keyword'];

    public function match_op_keyword($stack = [])
    {
        $matchrule = 'op_keyword';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11479 = \null;
        do {
            $stack[] = $result;
            $result = $this->construct($matchrule, 'not');
            $res_11477 = $result;
            $pos_11477 = $this->pos;
            if (($subres = $this->rx('/(DO(ES)?\s+NOT\s+)/')) !== \false) {
                $result['text'] .= $subres;
                $subres = $result;
                $result = \array_pop($stack);
                $this->store($result, $subres, 'not');
            } else {
                $result = $res_11477;
                $this->setPos($pos_11477);
                unset($res_11477, $pos_11477);
            }
            $stack[] = $result;
            $result = $this->construct($matchrule, 'key');
            if (($subres = $this->rx('/(CONTAINS?|MATCH(ES)?|STARTS?\s+WITH)/')) !== \false) {
                $result['text'] .= $subres;
                $subres = $result;
                $result = \array_pop($stack);
                $this->store($result, $subres, 'key');
            } else {
                $result = \array_pop($stack);
                $_11479 = \false;
                break;
            }
            $_11479 = \true;
            break;
        } while (\false);
        if (\true === $_11479) {
            return $this->finalise($result);
        }
        if (\false === $_11479) {
            return \false;
        }
    }

    public function op_keyword__finalise(&$result)
    {
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

    public function match_value_expression($stack = [])
    {
        $matchrule = 'value_expression';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $key = 'match_value_sum';
        $pos = $this->pos;
        $subres = $this->packhas($key, $pos)
            ? $this->packread($key, $pos)
            : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
        if (\false !== $subres) {
            $this->store($result, $subres, 'v');

            return $this->finalise($result);
        } else {
            return \false;
        }
    }

    public function value_expression__finalise(&$result)
    {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

    /* value_product: v:value_or_expr ( > sign:('/' | '*') > right:value_or_expr ) * */
    protected $match_value_product_typestack = ['value_product'];

    public function match_value_product($stack = [])
    {
        $matchrule = 'value_product';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11495 = \null;
        do {
            $key = 'match_value_or_expr';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'v');
            } else {
                $_11495 = \false;
                break;
            }
            while (\true) {
                $res_11494 = $result;
                $pos_11494 = $this->pos;
                $_11493 = \null;
                do {
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    }
                    $stack[] = $result;
                    $result = $this->construct($matchrule, 'sign');
                    $_11489 = \null;
                    do {
                        $_11487 = \null;
                        do {
                            $res_11484 = $result;
                            $pos_11484 = $this->pos;
                            if ('/' === \substr($this->string, $this->pos, 1)) {
                                $this->addPos(1);
                                $result['text'] .= '/';
                                $_11487 = \true;
                                break;
                            }
                            $result = $res_11484;
                            $this->setPos($pos_11484);
                            if ('*' === \substr($this->string, $this->pos, 1)) {
                                $this->addPos(1);
                                $result['text'] .= '*';
                                $_11487 = \true;
                                break;
                            }
                            $result = $res_11484;
                            $this->setPos($pos_11484);
                            $_11487 = \false;
                            break;
                        } while (\false);
                        if (\false === $_11487) {
                            $_11489 = \false;
                            break;
                        }
                        $_11489 = \true;
                        break;
                    } while (\false);
                    if (\true === $_11489) {
                        $subres = $result;
                        $result = \array_pop($stack);
                        $this->store($result, $subres, 'sign');
                    }
                    if (\false === $_11489) {
                        $result = \array_pop($stack);
                        $_11493 = \false;
                        break;
                    }
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    }
                    $key = 'match_value_or_expr';
                    $pos = $this->pos;
                    $subres = $this->packhas($key, $pos)
                        ? $this->packread($key, $pos)
                        : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                    if (\false !== $subres) {
                        $this->store($result, $subres, 'right');
                    } else {
                        $_11493 = \false;
                        break;
                    }
                    $_11493 = \true;
                    break;
                } while (\false);
                if (\false === $_11493) {
                    $result = $res_11494;
                    $this->setPos($pos_11494);
                    unset($res_11494, $pos_11494);
                    break;
                }
            }
            $_11495 = \true;
            break;
        } while (\false);
        if (\true === $_11495) {
            return $this->finalise($result);
        }
        if (\false === $_11495) {
            return \false;
        }
    }

    public function value_product_handleOperator(mixed $l, mixed $r, string $operator): array|int|float
    {
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

    public function value_product__finalise(&$result)
    {
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

    public function match_value_sum($stack = [])
    {
        $matchrule = 'value_sum';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11510 = \null;
        do {
            $key = 'match_value_product';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'v');
            } else {
                $_11510 = \false;
                break;
            }
            while (\true) {
                $res_11509 = $result;
                $pos_11509 = $this->pos;
                $_11508 = \null;
                do {
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    }
                    $stack[] = $result;
                    $result = $this->construct($matchrule, 'sign');
                    $_11504 = \null;
                    do {
                        $_11502 = \null;
                        do {
                            $res_11499 = $result;
                            $pos_11499 = $this->pos;
                            if ('+' === \substr($this->string, $this->pos, 1)) {
                                $this->addPos(1);
                                $result['text'] .= '+';
                                $_11502 = \true;
                                break;
                            }
                            $result = $res_11499;
                            $this->setPos($pos_11499);
                            if ('-' === \substr($this->string, $this->pos, 1)) {
                                $this->addPos(1);
                                $result['text'] .= '-';
                                $_11502 = \true;
                                break;
                            }
                            $result = $res_11499;
                            $this->setPos($pos_11499);
                            $_11502 = \false;
                            break;
                        } while (\false);
                        if (\false === $_11502) {
                            $_11504 = \false;
                            break;
                        }
                        $_11504 = \true;
                        break;
                    } while (\false);
                    if (\true === $_11504) {
                        $subres = $result;
                        $result = \array_pop($stack);
                        $this->store($result, $subres, 'sign');
                    }
                    if (\false === $_11504) {
                        $result = \array_pop($stack);
                        $_11508 = \false;
                        break;
                    }
                    if (($subres = $this->whitespace()) !== \false) {
                        $result['text'] .= $subres;
                    }
                    $key = 'match_value_product';
                    $pos = $this->pos;
                    $subres = $this->packhas($key, $pos)
                        ? $this->packread($key, $pos)
                        : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                    if (\false !== $subres) {
                        $this->store($result, $subres, 'right');
                    } else {
                        $_11508 = \false;
                        break;
                    }
                    $_11508 = \true;
                    break;
                } while (\false);
                if (\false === $_11508) {
                    $result = $res_11509;
                    $this->setPos($pos_11509);
                    unset($res_11509, $pos_11509);
                    break;
                }
            }
            $_11510 = \true;
            break;
        } while (\false);
        if (\true === $_11510) {
            return $this->finalise($result);
        }
        if (\false === $_11510) {
            return \false;
        }
    }

    public function value_sum_handleOperator(mixed $l, mixed $r, string $operator): array|int|float
    {
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

    public function value_sum__finalise(&$result)
    {
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

    public function match_value_or_expr($stack = [])
    {
        $matchrule = 'value_or_expr';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11521 = \null;
        do {
            $res_11512 = $result;
            $pos_11512 = $this->pos;
            $key = 'match_value';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'v');
                $_11521 = \true;
                break;
            }
            $result = $res_11512;
            $this->setPos($pos_11512);
            $_11519 = \null;
            do {
                if ('(' === \substr($this->string, $this->pos, 1)) {
                    $this->addPos(1);
                    $result['text'] .= '(';
                } else {
                    $_11519 = \false;
                    break;
                }
                if (($subres = $this->whitespace()) !== \false) {
                    $result['text'] .= $subres;
                }
                $key = 'match_value_expression';
                $pos = $this->pos;
                $subres = $this->packhas($key, $pos)
                    ? $this->packread($key, $pos)
                    : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                if (\false !== $subres) {
                    $this->store($result, $subres, 'v');
                } else {
                    $_11519 = \false;
                    break;
                }
                if (($subres = $this->whitespace()) !== \false) {
                    $result['text'] .= $subres;
                }
                if (')' === \substr($this->string, $this->pos, 1)) {
                    $this->addPos(1);
                    $result['text'] .= ')';
                } else {
                    $_11519 = \false;
                    break;
                }
                $_11519 = \true;
                break;
            } while (\false);
            if (\true === $_11519) {
                $_11521 = \true;
                break;
            }
            $result = $res_11512;
            $this->setPos($pos_11512);
            $_11521 = \false;
            break;
        } while (\false);
        if (\true === $_11521) {
            return $this->finalise($result);
        }
        if (\false === $_11521) {
            return \false;
        }
    }

    public function value_or_expr__finalise(&$result)
    {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

    /* value: v:number | v:quoted_string | v:boolean | v:field */
    protected $match_value_typestack = ['value'];

    public function match_value($stack = [])
    {
        $matchrule = 'value';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11534 = \null;
        do {
            $res_11523 = $result;
            $pos_11523 = $this->pos;
            $key = 'match_number';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'v');
                $_11534 = \true;
                break;
            }
            $result = $res_11523;
            $this->setPos($pos_11523);
            $_11532 = \null;
            do {
                $res_11525 = $result;
                $pos_11525 = $this->pos;
                $key = 'match_quoted_string';
                $pos = $this->pos;
                $subres = $this->packhas($key, $pos)
                    ? $this->packread($key, $pos)
                    : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                if (\false !== $subres) {
                    $this->store($result, $subres, 'v');
                    $_11532 = \true;
                    break;
                }
                $result = $res_11525;
                $this->setPos($pos_11525);
                $_11530 = \null;
                do {
                    $res_11527 = $result;
                    $pos_11527 = $this->pos;
                    $key = 'match_boolean';
                    $pos = $this->pos;
                    $subres = $this->packhas($key, $pos)
                        ? $this->packread($key, $pos)
                        : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                    if (\false !== $subres) {
                        $this->store($result, $subres, 'v');
                        $_11530 = \true;
                        break;
                    }
                    $result = $res_11527;
                    $this->setPos($pos_11527);
                    $key = 'match_field';
                    $pos = $this->pos;
                    $subres = $this->packhas($key, $pos)
                        ? $this->packread($key, $pos)
                        : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                    if (\false !== $subres) {
                        $this->store($result, $subres, 'v');
                        $_11530 = \true;
                        break;
                    }
                    $result = $res_11527;
                    $this->setPos($pos_11527);
                    $_11530 = \false;
                    break;
                } while (\false);
                if (\true === $_11530) {
                    $_11532 = \true;
                    break;
                }
                $result = $res_11525;
                $this->setPos($pos_11525);
                $_11532 = \false;
                break;
            } while (\false);
            if (\true === $_11532) {
                $_11534 = \true;
                break;
            }
            $result = $res_11523;
            $this->setPos($pos_11523);
            $_11534 = \false;
            break;
        } while (\false);
        if (\true === $_11534) {
            return $this->finalise($result);
        }
        if (\false === $_11534) {
            return \false;
        }
    }

    public function value__finalise(&$result)
    {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

    /* int: /[0-9]+/ */
    protected $match_int_typestack = ['int'];

    public function match_int($stack = [])
    {
        $matchrule = 'int';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        if (($subres = $this->rx('/[0-9]+/')) !== \false) {
            $result['text'] .= $subres;

            return $this->finalise($result);
        } else {
            return \false;
        }
    }

    public function int__finalise(&$result)
    {
        $result['data'] = (int) $result['text'];
    }

    /* decimal: int? "." int */
    protected $match_decimal_typestack = ['decimal'];

    public function match_decimal($stack = [])
    {
        $matchrule = 'decimal';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11540 = \null;
        do {
            $res_11537 = $result;
            $pos_11537 = $this->pos;
            $key = 'match_int';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres);
            } else {
                $result = $res_11537;
                $this->setPos($pos_11537);
                unset($res_11537, $pos_11537);
            }
            if ('.' === \substr($this->string, $this->pos, 1)) {
                $this->addPos(1);
                $result['text'] .= '.';
            } else {
                $_11540 = \false;
                break;
            }
            $key = 'match_int';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres);
            } else {
                $_11540 = \false;
                break;
            }
            $_11540 = \true;
            break;
        } while (\false);
        if (\true === $_11540) {
            return $this->finalise($result);
        }
        if (\false === $_11540) {
            return \false;
        }
    }

    public function decimal__finalise(&$result)
    {
        $result['data'] = (float) $result['text'];
    }

    /* quoted_string: /"[^"]*"/ */
    protected $match_quoted_string_typestack = ['quoted_string'];

    public function match_quoted_string($stack = [])
    {
        $matchrule = 'quoted_string';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        if (($subres = $this->rx('/"[^"]*"/')) !== \false) {
            $result['text'] .= $subres;

            return $this->finalise($result);
        } else {
            return \false;
        }
    }

    public function quoted_string__finalise(&$result)
    {
        $result['data'] = ['literal' => substr($result['text'], 1, -1)];
    }

    /* number: v:int | v:decimal */
    protected $match_number_typestack = ['number'];

    public function match_number($stack = [])
    {
        $matchrule = 'number';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11546 = \null;
        do {
            $res_11543 = $result;
            $pos_11543 = $this->pos;
            $key = 'match_int';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'v');
                $_11546 = \true;
                break;
            }
            $result = $res_11543;
            $this->setPos($pos_11543);
            $key = 'match_decimal';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres, 'v');
                $_11546 = \true;
                break;
            }
            $result = $res_11543;
            $this->setPos($pos_11543);
            $_11546 = \false;
            break;
        } while (\false);
        if (\true === $_11546) {
            return $this->finalise($result);
        }
        if (\false === $_11546) {
            return \false;
        }
    }

    public function number__finalise(&$result)
    {
        $result['data'] = $result['v']['data'];
        unset($result['v']);
    }

    /* alpha: /[a-zA-Z_]/ */
    protected $match_alpha_typestack = ['alpha'];

    public function match_alpha($stack = [])
    {
        $matchrule = 'alpha';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        if (($subres = $this->rx('/[a-zA-Z_]/')) !== \false) {
            $result['text'] .= $subres;

            return $this->finalise($result);
        } else {
            return \false;
        }
    }

    /* alphanum: /[a-zA-Z_0-9-]/ */
    protected $match_alphanum_typestack = ['alphanum'];

    public function match_alphanum($stack = [])
    {
        $matchrule = 'alphanum';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        if (($subres = $this->rx('/[a-zA-Z_0-9-]/')) !== \false) {
            $result['text'] .= $subres;

            return $this->finalise($result);
        } else {
            return \false;
        }
    }

    /* keyword: alpha alphanum* */
    protected $match_keyword_typestack = ['keyword'];

    public function match_keyword($stack = [])
    {
        $matchrule = 'keyword';
        $this->currentRule = $matchrule;
        $result = $this->construct($matchrule, $matchrule);
        $_11552 = \null;
        do {
            $key = 'match_alpha';
            $pos = $this->pos;
            $subres = $this->packhas($key, $pos)
                ? $this->packread($key, $pos)
                : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
            if (\false !== $subres) {
                $this->store($result, $subres);
            } else {
                $_11552 = \false;
                break;
            }
            while (\true) {
                $res_11551 = $result;
                $pos_11551 = $this->pos;
                $key = 'match_alphanum';
                $pos = $this->pos;
                $subres = $this->packhas($key, $pos)
                    ? $this->packread($key, $pos)
                    : $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
                if (\false !== $subres) {
                    $this->store($result, $subres);
                } else {
                    $result = $res_11551;
                    $this->setPos($pos_11551);
                    unset($res_11551, $pos_11551);
                    break;
                }
            }
            $_11552 = \true;
            break;
        } while (\false);
        if (\true === $_11552) {
            return $this->finalise($result);
        }
        if (\false === $_11552) {
            return \false;
        }
    }
}

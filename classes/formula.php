<?php
namespace local_gradesheet;

defined('MOODLE_INTERNAL') || die();

/**
 * Safe evaluator for faculty-entered transmutation formulas.
 *
 * The formula is an arithmetic expression in one variable, P (the raw
 * percentage, 0-100). Supported: numbers, + - * / % ^, parentheses, unary
 * minus, and the functions min(), max(), round(), floor(), ceil(), abs(),
 * sqrt(). Nothing is passed to PHP's eval(); the expression is tokenised
 * and parsed by a small recursive-descent parser, so a formula can only ever
 * compute a number.
 *
 * Examples:
 *   1 + (100 - P) * 0.08        ESSU-style linear 1.0-5.0 (100->1.0, 75->3.0, 50->5.0)
 *   min(95, 50 + P / 2)         base-50 transmutation capped at 95
 *   min(95, P)                  raw percentage capped at 95
 */
class formula {

    /** @var array Tokens produced by tokenize(). */
    private array $tokens = [];
    /** @var int Current token position. */
    private int $pos = 0;
    /** @var float Value of P for this evaluation. */
    private float $p = 0.0;

    private const FUNCTIONS = ['min', 'max', 'round', 'floor', 'ceil', 'abs', 'sqrt'];

    /**
     * Evaluate $expression with P = $p.
     *
     * @throws \InvalidArgumentException on a malformed expression.
     */
    public static function evaluate(string $expression, float $p): float {
        $f = new self();
        $f->tokens = self::tokenize($expression);
        $f->pos = 0;
        $f->p = $p;
        if (empty($f->tokens)) {
            throw new \InvalidArgumentException('Formula is empty.');
        }
        $value = $f->parse_expression();
        if ($f->pos < count($f->tokens)) {
            throw new \InvalidArgumentException('Unexpected "' . $f->tokens[$f->pos][1] . '" in formula.');
        }
        if (!is_finite($value)) {
            throw new \InvalidArgumentException('Formula produced a non-finite number.');
        }
        return (float)$value;
    }

    /**
     * Validate an expression by parsing it and evaluating it at a few sample
     * points. Returns an error message, or '' when the formula is usable.
     */
    public static function validate(string $expression): string {
        $expression = trim($expression);
        if ($expression === '') {
            return 'Formula is empty.';
        }
        if (mb_strlen($expression) > 255) {
            return 'Formula must be 255 characters or fewer.';
        }
        try {
            foreach ([0.0, 50.0, 74.99, 75.0, 100.0] as $p) {
                self::evaluate($expression, $p);
            }
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        } catch (\DivisionByZeroError $e) {
            return 'Formula divides by zero.';
        }
        return '';
    }

    /** Splits the expression into [type, text] tokens. */
    private static function tokenize(string $expr): array {
        $tokens = [];
        $len = strlen($expr);
        $i = 0;
        while ($i < $len) {
            $c = $expr[$i];
            if (ctype_space($c)) {
                $i++;
                continue;
            }
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($expr[$i + 1]))) {
                $start = $i;
                while ($i < $len && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    $i++;
                }
                $num = substr($expr, $start, $i - $start);
                if (!is_numeric($num)) {
                    throw new \InvalidArgumentException('Invalid number "' . $num . '".');
                }
                $tokens[] = ['num', $num];
                continue;
            }
            if (ctype_alpha($c)) {
                $start = $i;
                while ($i < $len && ctype_alpha($expr[$i])) {
                    $i++;
                }
                $word = strtolower(substr($expr, $start, $i - $start));
                if ($word === 'p' || $word === 'x' || $word === 'score') {
                    $tokens[] = ['var', 'P'];
                } else if (in_array($word, self::FUNCTIONS, true)) {
                    $tokens[] = ['func', $word];
                } else {
                    throw new \InvalidArgumentException('Unknown name "' . $word . '". Use P for the raw percentage.');
                }
                continue;
            }
            if (strpos('+-*/%^(),', $c) !== false) {
                $tokens[] = ['op', $c];
                $i++;
                continue;
            }
            throw new \InvalidArgumentException('Unexpected character "' . $c . '" in formula.');
        }
        return $tokens;
    }

    private function peek(): ?array {
        return $this->tokens[$this->pos] ?? null;
    }

    private function take(): array {
        $t = $this->peek();
        if ($t === null) {
            throw new \InvalidArgumentException('Formula ends unexpectedly.');
        }
        $this->pos++;
        return $t;
    }

    private function expect_op(string $op): void {
        $t = $this->take();
        if ($t[0] !== 'op' || $t[1] !== $op) {
            throw new \InvalidArgumentException('Expected "' . $op . '" but found "' . $t[1] . '".');
        }
    }

    // expression := term (('+' | '-') term)*
    private function parse_expression(): float {
        $value = $this->parse_term();
        while (($t = $this->peek()) && $t[0] === 'op' && ($t[1] === '+' || $t[1] === '-')) {
            $this->pos++;
            $rhs = $this->parse_term();
            $value = ($t[1] === '+') ? $value + $rhs : $value - $rhs;
        }
        return $value;
    }

    // term := power (('*' | '/' | '%') power)*
    private function parse_term(): float {
        $value = $this->parse_power();
        while (($t = $this->peek()) && $t[0] === 'op' && in_array($t[1], ['*', '/', '%'], true)) {
            $this->pos++;
            $rhs = $this->parse_power();
            if ($t[1] === '*') {
                $value *= $rhs;
            } else {
                if ($rhs == 0.0) {
                    throw new \DivisionByZeroError('Division by zero');
                }
                $value = ($t[1] === '/') ? $value / $rhs : fmod($value, $rhs);
            }
        }
        return $value;
    }

    // power := unary ('^' power)?   (right-associative)
    private function parse_power(): float {
        $base = $this->parse_unary();
        if (($t = $this->peek()) && $t[0] === 'op' && $t[1] === '^') {
            $this->pos++;
            $exp = $this->parse_power();
            return pow($base, $exp);
        }
        return $base;
    }

    // unary := ('-' | '+') unary | primary
    private function parse_unary(): float {
        $t = $this->peek();
        if ($t && $t[0] === 'op' && ($t[1] === '-' || $t[1] === '+')) {
            $this->pos++;
            $v = $this->parse_unary();
            return $t[1] === '-' ? -$v : $v;
        }
        return $this->parse_primary();
    }

    // primary := number | P | '(' expression ')' | func '(' args ')'
    private function parse_primary(): float {
        $t = $this->take();
        switch ($t[0]) {
            case 'num':
                return (float)$t[1];
            case 'var':
                return $this->p;
            case 'func':
                $this->expect_op('(');
                $args = [$this->parse_expression()];
                while (($n = $this->peek()) && $n[0] === 'op' && $n[1] === ',') {
                    $this->pos++;
                    $args[] = $this->parse_expression();
                }
                $this->expect_op(')');
                return $this->call($t[1], $args);
            case 'op':
                if ($t[1] === '(') {
                    $v = $this->parse_expression();
                    $this->expect_op(')');
                    return $v;
                }
                // Fall through.
            default:
                throw new \InvalidArgumentException('Unexpected "' . $t[1] . '" in formula.');
        }
    }

    private function call(string $name, array $args): float {
        $n = count($args);
        switch ($name) {
            case 'min':
            case 'max':
                if ($n < 2) {
                    throw new \InvalidArgumentException($name . '() needs at least two values.');
                }
                return $name === 'min' ? min($args) : max($args);
            case 'round':
                if ($n < 1 || $n > 2) {
                    throw new \InvalidArgumentException('round() takes one or two values.');
                }
                return round($args[0], $n === 2 ? (int)$args[1] : 0);
            case 'floor':
            case 'ceil':
            case 'abs':
            case 'sqrt':
                if ($n !== 1) {
                    throw new \InvalidArgumentException($name . '() takes exactly one value.');
                }
                if ($name === 'sqrt' && $args[0] < 0) {
                    throw new \InvalidArgumentException('sqrt() of a negative number.');
                }
                return $name($args[0]);
        }
        throw new \InvalidArgumentException('Unknown function "' . $name . '".');
    }
}

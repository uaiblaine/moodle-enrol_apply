# QM8: the first paint carries `show` as the address had it, on any queue.
s#'show' => \$kind === 'site' && optional_param\(self::SHOW, '', PARAM_ALPHA\) === self::METHODS \? self::METHODS : '',#'show' => optional_param(self::SHOW, '', PARAM_ALPHA),#;

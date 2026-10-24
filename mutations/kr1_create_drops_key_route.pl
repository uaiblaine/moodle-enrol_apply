# KR1: create() is handed the key route and writes none of it.
s#'keygroupid' => max\(0, \$keygroupid\),\n(\s*)'keyoutcome' => \$keyoutcome,#'keygroupid' => 0,\n${1}'keyoutcome' => self::KEY_NONE,#;

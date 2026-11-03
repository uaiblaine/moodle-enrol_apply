# KT1: key-only mode is not warned about a course whose groups carry no key.
s#if \(!\$keyed\) \{\n(\s*)\$warnings\[\]#if (false) \{\n${1}\$warnings[]#;

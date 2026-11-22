# RT2: no retention notice where records are kept forever.
s#if \(\$seconds <= 0\) \{\n            return null;#if (false) {\n            return null;#;

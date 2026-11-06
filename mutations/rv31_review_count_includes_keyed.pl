# RV31: the reviewed mode counts the keyed applications among the ones it leaves waiting.
s#if \(\$review\) \{\n(\s*)// Only the applications made without a key#if (false) {\n${1}// Only the applications made without a key#;

# KO1: a key-only instance without a valid key writes an application waiting for nobody.
s#if \(\$keygroupid === null\) \{\n(\s*)if \(\$mandatory\) \{#if (\$keygroupid === null) \{\n${1}if (false) \{#;

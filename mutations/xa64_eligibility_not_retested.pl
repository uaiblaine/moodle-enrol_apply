# XA64: allow_apply() is not asked again under the locks.
s#(\$allowapply = \$this->allow_apply\(\$current, \$userid\);\n\s+)if \(\$allowapply !== true\) \{#$1if (false) {#;

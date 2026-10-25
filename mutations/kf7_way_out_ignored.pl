# KF7: validation refuses the wrong key even with "without the key" ticked.
s#if \(\$this->_form->elementExists\('groupkey'\) && empty\(\$data\['withoutkey'\]\)\) \{#if (\$this->_form->elementExists('groupkey')) \{#;

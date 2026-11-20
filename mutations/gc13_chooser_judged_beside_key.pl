# GC13: the chooser is judged though a valid key wins over it.
s#if \(\$this->_form->elementExists\('chosengroup'\) && !\$this->key_wins\(\$data\)\) \{#if (\$this->_form->elementExists('chosengroup')) {#;

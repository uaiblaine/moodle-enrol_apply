# KQ5: Continue runs the whole validation, so step 2 arrives with its fields in error.
s#if \(!\$this->is_continue\(\)\) \{\n(\s*)return parent::is_validated\(\);#if (true) \{\n${1}return parent::is_validated();#;

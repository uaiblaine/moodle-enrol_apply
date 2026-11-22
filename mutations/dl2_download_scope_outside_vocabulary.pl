# DL2: the report reads its scope parameter through the engine's vocabulary.
s#\$scope = reportengine::scope\(\(string\) \$this->get_parameter\('scope', 'all', PARAM_ALPHA\)\);#\$scope = (string) \$this->get_parameter('scope', 'all', PARAM_ALPHA);#;

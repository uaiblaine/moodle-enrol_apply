# BT: make the memo static. enrol_get_plugins() builds a fresh plugin object per call, so a static
# outlives the manager it belongs to: the menu is silenced for the rest of the request and, in a
# PHPUnit run, for every later test in the same process.
s#    protected \$bulkmenuoffered = false;#    protected static \$bulkmenuoffered = false;#;
s#            if \(\$this->bulkmenuoffered\) \{#            if (self::\$bulkmenuoffered) {#;
s#            \$this->bulkmenuoffered = true;#            self::\$bulkmenuoffered = true;#;

# BO: offer the menu on a site-disabled plugin again. user/index.php builds it from the
# include-disabled list while action_redir.php resolves the dispatch from the enabled-only one and
# throws errorwithbulkoperation, so every entry leads to an exception page.
s#        if \(!enrol_is_enabled\(\$this->get_name\(\)\)\) \{\n            return \[\];\n        \}\n\n##s;

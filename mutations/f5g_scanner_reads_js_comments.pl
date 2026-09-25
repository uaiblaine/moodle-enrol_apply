# F5G: the markup scan reads JavaScript comments as markup again.
s{'js' => \$this->strip_js_comments\(\$source\),}{'js' => \$source,}s;

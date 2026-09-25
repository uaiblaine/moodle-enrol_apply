# F5E: the markup scan reads PHP comments as markup again.
s{'php' => \$this->strip_php_comments\(\$source\),}{'php' => \$source,}s;

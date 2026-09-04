# DD: stop the field and date filters riding the base url. Page one of a filtered queue is correct
# and page two is a different queue, and so is every sort. No row-level assertion can see it.
s{        foreach \(\$this->fieldfilters as \$token => \$value\) \{\n            \$params\[\$token\] = \$value;\n        \}\n        if \(\$this->appliedfrom !== null\) \{\n            \$params\['appliedfrom'\] = \$this->appliedfrom;\n        \}\n        if \(\$this->appliedto !== null\) \{\n            \$params\['appliedto'\] = \$this->appliedto;\n        \}\n}{}s;

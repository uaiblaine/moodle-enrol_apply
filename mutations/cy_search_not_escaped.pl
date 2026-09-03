# CY: let the search term reach LIKE unescaped. A percent sign an applicant typed becomes a
# wildcard, so "100%" matches every row and an operator hunting one application is handed the whole
# queue with no indication why. Core's own participants search has this defect; copying its
# function copies the bug.
s{\$value = '%' \. \$DB->sql_like_escape\(\$this->search\) \. '%';}{\$value = '%' . \$this->search . '%';}s;

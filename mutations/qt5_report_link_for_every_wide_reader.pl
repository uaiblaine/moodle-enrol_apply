# QT5: "Open the report" is given on the queues that span courses too (a link to report.php?id=0), a mentor's included.
s#(\$reporturl = )'';(\n\s*if \(\$this->instance !== null\) \{)#$1report_view::url(\$enrolid)->out(false);$2#;

# BD: drop the read-time fallback for the notification wording. Every one of the six settings
# ships empty and e-mail is on by default, so a stock site sends its applicants a message with
# no subject and no body - measured on m502, 14 of 14.
s{\n        \[\$defaultsubject, \$defaultcontent\] = \$this->default_notification\(\$type, \$course\);\n        if \(trim\(\(string\) \$subject\) === ''\) \{\n            \$subject = \$defaultsubject;\n        \}\n        if \(trim\(\(string\) \$content\) === ''\) \{\n            \$content = \$defaultcontent;\n        \}\n}{\n}s;

# XA1: any truthy submission marker counts as a submission, where formslib reads only a 1.
s#return array_key_exists\(\$marker, \$submission\) && \$submission\[\$marker\] == 1 \? \$submission : \[\];#return empty(\$submission[\$marker]) ? [] : \$submission;#;

# PL8: the queue links every applicant's name to user/view.php again.
s#\$name = isset\(\$this->profilelinks#\$name = true || isset(\$this->profilelinks#;

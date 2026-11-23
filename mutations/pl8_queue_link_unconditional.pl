# PL8: the queue links every applicant's name to user/view.php again.
# Re-pointed when the link moved to applications::profile_url(), read by the cell and the headless reader.
s#if \(!isset\(\$this->profilelinks\[#if (false && !isset(\$this->profilelinks[#;

# XA57: the cancellation question puts the applicant's name into confirm()'s raw message unescaped.
s#'reviewcancelconfirm_desc', 'enrol_apply', s\(fullname\(\$applicant\)\)\)#'reviewcancelconfirm_desc', 'enrol_apply', fullname(\$applicant))#;

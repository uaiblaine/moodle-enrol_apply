# QG17: clear-all drops the group parameter, which the page reads as "apply the default".
s#'clearurl' => \$groupdefault !== null#'clearurl' => false#;

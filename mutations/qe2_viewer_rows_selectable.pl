# QE2: a row is selectable for a reader who may not decide from the listing (a viewer).
s#return \$this->can_decide\(\) && !queue::is_own_application#return !queue::is_own_application#;

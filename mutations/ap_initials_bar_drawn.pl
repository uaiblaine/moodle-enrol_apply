# AP: let the caller re-arm the A-Z bar. The filter would still be dead, so the page would carry
# a control that silently does nothing when clicked - worse than either end state.
s!    public function initialbars\(\$bool\) \{\n        parent::initialbars\(false\);!    public function initialbars(\$bool) \{\n        parent::initialbars(\$bool);!s;

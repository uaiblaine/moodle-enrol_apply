# AU: drop the panel describing the decision a colleague already took, leaving a deferred
# application saying only that it is deferred - which is what the page did before.
s#\n            \+ \$this->decision_context\(\$application\)##s;

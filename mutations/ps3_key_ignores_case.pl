# PS3: a scenario's key is matched without regard to case.
s#return match \(\$requested\) \{#return match (strtolower(\$requested)) {#;

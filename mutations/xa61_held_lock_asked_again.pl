# XA61: lock_admission() asks for the method's lock even when submit_application() holds it.
s#if \(!\$this->instancelock\) \{#if (true) {#;

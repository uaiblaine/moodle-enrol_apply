# XA11: the hook judges a status change alone, so an end date written on an application by somebody who may not decide it goes through.
s#if \(!\$before \|\| !self::is_application\(\$before\)\) \{#if (!\$before || !self::is_application(\$before) || !\$hook->statusmodified) {#;

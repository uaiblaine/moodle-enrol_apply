# RV25: validation reads required(), so the reviewed mode's wrong key gets the way-out wording.
s#\$required = groupkey::mandatory\(\$this->get_instance\(\)\);\n(\s*)if \(\$this->_form->elementExists\('groupkey'\) && empty\(\$data\['withoutkey'\]\)\) \{#\$required = groupkey::required(\$this->get_instance());\n${1}if (\$this->_form->elementExists('groupkey') && empty(\$data['withoutkey'])) {#;

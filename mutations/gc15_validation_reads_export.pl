# GC15: validation reads the exported value, which drops an id outside the options.
s#groupchoice::posted\(\(object\) \['chosengroup' => \$this->_form->getSubmitValue\('chosengroup'\)\]\)#groupchoice::posted((object) ['chosengroup' => \$data['chosengroup'] ?? null])#;

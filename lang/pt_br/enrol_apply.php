<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for the enrolment upon approval plugin.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @copyright  emeneo.com (http://emeneo.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['admissionnotice'] = 'Avisar os gestores a cada inscrição com chave';
$string['admissionnotice_help'] = 'Só para um método que inscreve apenas com a chave de grupo.

* **Não**: ninguém é avisado de cada inscrição. É o padrão, porque nesse método toda inscrição é uma delas.
* **Sim**: quem é avisado das novas solicitações deste método também recebe um aviso curto cada vez que alguém se inscreve com uma chave.

Um método que também recebe solicitações para análise sempre envia o aviso.';
$string['admissionroute'] = 'Via de admissão';
$string['admissionroutereview'] = 'Análise, sem chave de grupo';
$string['admittedbykey'] = 'Você está inscrito. Sua chave de grupo foi aceita e você entrou no grupo {$a}. Não foi necessária análise.';
$string['admittedbykeynotification'] = 'Uma chave de inscrição de grupo inscreveu alguém em um curso sem análise.';
$string['allowedfields'] = 'Campos de perfil que os cursos podem solicitar';
$string['allowedfields_desc'] = 'O conjunto de campos de perfil que um curso pode coletar em uma solicitação de inscrição. O professor escolhe nesta lista em cada método de inscrição; desmarcar um campo aqui também o remove de todos os métodos existentes, pois o conjunto escolhido é verificado contra esta lista a cada uso.';
$string['allowprofilewrite'] = 'Permitir que cursos ofereçam salvar dados do perfil';
$string['allowprofilewrite_desc'] = 'Quando ativado, um método de inscrição pode oferecer ao solicitante a opção de salvar no próprio perfil os dados que ele informou. O solicitante é sempre consultado antes, e apenas os campos que ele pode editar são gravados. Quando desativado, os solicitantes veem quais dados estão faltando e são encaminhados à página do perfil.';
$string['answerfieldkey'] = 'Chave do campo';
$string['answerquestion'] = 'Pergunta';
$string['answervalue'] = 'Resposta';
$string['applicationadmittedbykey'] = 'Inscrito com a sua chave de grupo';
$string['applicationadmittedbykey_body'] = 'Sua chave de inscrição de grupo foi aceita: você está inscrito neste curso e entrou no grupo {$a}.';
$string['applicationapproved'] = 'Solicitação aprovada';
$string['applicationapproved_body'] = 'Sua solicitação de inscrição foi aprovada e sua inscrição está ativa. Você já pode entrar no curso.';
$string['applicationcancelednotification'] = 'Sua solicitação de inscrição no curso foi cancelada.';
$string['applicationchosengroup_body'] = 'Sua solicitação para {$a} aguarda decisão. Você será avisado assim que ela for analisada.';
$string['applicationconfirmednotification'] = 'Sua solicitação de inscrição no curso foi confirmada.';
$string['applicationdeferred'] = 'Solicitação adiada';
$string['applicationdeferred_body'] = 'Sua solicitação de inscrição foi adiada. Nenhuma decisão foi tomada ainda: ela aguarda uma vaga ou a verificação de alguma informação. Você será avisado por e-mail quando ela for decidida.';
$string['applicationdeferrednotification'] = 'Sua solicitação de inscrição no curso foi adiada.';
$string['applicationfields'] = 'Campos da solicitação';
$string['applicationfields_desc'] = 'Perguntas que este método de inscrição faz quando alguém solicita a inscrição, além dos campos de perfil solicitados. As respostas ficam guardadas com a solicitação e são mostradas a quem a avalia; nunca são gravadas no perfil de quem fez a solicitação. Categorias compartilhadas definidas para o site todo podem ser ativadas para este método.';
$string['applicationgone'] = 'Esta solicitação de inscrição não está mais aguardando decisão. Ela pode já ter sido decidida, ou a inscrição pode ter sido removida.';
$string['applicationinactive'] = 'A inscrição não está ativa';
$string['applicationinactive_body'] = 'Sua solicitação de inscrição foi aprovada, mas sua inscrição neste curso não está ativa e você não consegue entrar nele. Entre em contato com o professor do curso ou com o administrador do site.';
$string['applicationkeyforreview_body'] = 'Sua chave de {$a} foi aceita. Sua solicitação aguarda análise, e você será avisado assim que ela for analisada.';
$string['applicationkeygroupfull_body'] = 'Sua chave de {$a} é válida, mas esse grupo não tem mais vagas. Sua solicitação aguarda uma decisão, e você será avisado assim que ela for analisada.';
$string['applicationkeynoplace_body'] = 'Sua chave de {$a} é válida, mas este curso não tem mais vagas. Sua solicitação aguarda uma decisão, e você será avisado assim que ela for analisada.';
$string['applicationkeyunconfirmed_body'] = 'Sua chave de {$a} é válida, mas não foi possível confirmar uma vaga para você agora. Sua solicitação aguarda uma decisão, e você será avisado assim que ela for analisada.';
$string['applicationsclosednotice'] = 'Este método de inscrição tem {$a->held} de no máximo {$a->limit} solicitações e não aceita mais nenhuma. {$a->deferred} delas estão adiadas, e uma solicitação adiada não é liberada por nada: cancele as que você não precisa mais para abrir espaço.';
$string['applicationsnonedecided'] = 'Nenhuma solicitação foi decidida. As que você selecionou não aguardam mais decisão, ou não cabe a você decidi-las.';
$string['applicationsskipped'] = 'Solicitações deixadas como estavam, porque não aguardavam mais decisão ou não cabia a você decidi-las: {$a}';
$string['applicationsubmitted'] = 'Solicitação enviada';
$string['applicationsubmitted_body'] = 'Sua solicitação foi enviada e aguarda decisão. Você será avisado assim que ela for analisada.';
$string['applicationsupdated'] = 'As solicitações de inscrição selecionadas foram atualizadas.';
$string['apply:choosedecision'] = 'Escolher os grupos e o papel que uma aprovação concede';
$string['apply:config'] = 'Configurar instâncias de inscrição mediante aprovação';
$string['apply:manage'] = 'Gerenciar inscrições de usuários';
$string['apply:manageapplications'] = 'Gerenciar solicitações de inscrição';
$string['apply:unenrol'] = 'Cancelar a inscrição de usuários no curso';
$string['apply:unenrolself'] = 'Cancelar a própria inscrição no curso';
$string['apply:viewapplications'] = 'Ver solicitações de inscrição sem decidir';
$string['apply:viewreports'] = 'Ver o relatório de solicitações de inscrição';
$string['applycomment'] = 'Comentário';
$string['applycomment_required'] = 'Um comentário para o professor';
$string['applycourselocked'] = 'Outra solicitação sua para este curso está sendo enviada. Tente novamente em instantes.';
$string['applydate'] = 'Data da solicitação';
$string['applydecidingelsewhere'] = 'Você já se candidatou a participar deste curso em outro papel, por isso não pode se candidatar aqui.';
$string['applylimitlocked'] = 'As solicitações deste curso estão sendo processadas. Tente novamente em instantes.';
$string['applymanage'] = 'Gerenciar solicitações de inscrição';
$string['applyuser'] = 'Nome / Sobrenome';
$string['applywithkey'] = 'Solicitar inscrição com chave';
$string['backtoapplications'] = 'Voltar às solicitações de inscrição';
$string['btncancel'] = 'Cancelar solicitações';
$string['btncancelshort'] = 'Cancelar';
$string['btnconfirm'] = 'Confirmar';
$string['btnconfirmshort'] = 'Confirmar';
$string['btnwait'] = 'Adiar';
$string['btnwaitshort'] = 'Adiar';
$string['bulkapplicants'] = 'Solicitantes selecionados';
$string['bulkcancel'] = 'Cancelar solicitações de inscrição';
$string['bulkcanceldesc'] = 'As solicitações listadas abaixo serão canceladas e os solicitantes terão a inscrição cancelada. Quem estiver na seleção sem aguardar uma decisão não será alterado.';
$string['bulkconfirm'] = 'Confirmar solicitações de inscrição';
$string['bulkconfirmdesc'] = 'As solicitações listadas abaixo serão confirmadas e os solicitantes inscritos. Quem estiver na seleção sem aguardar uma decisão não será alterado.';
$string['bulkdecided'] = 'Solicitações de inscrição decididas: {$a}';
$string['bulknothingdecided'] = 'Nenhum dos usuários selecionados tinha solicitação a decidir.';
$string['bulknotpermitted'] = 'Você não pode decidir solicitações de inscrição neste curso.';
$string['bulkothermethods'] = 'Uma decisão em massa por esta página alcança um método de inscrição, e esta alcançou "{$a->method}". Solicitações das mesmas pessoas em outros métodos deste curso, deixadas como estavam: {$a->count}';
$string['bulkothermethodsform'] = 'Uma decisão em massa por esta página alcança um método de inscrição, e esta alcança "{$a->method}". As pessoas que você selecionou têm mais {$a->count} em outros métodos deste curso, e essas ficarão como estão. Decida-as pela fila de aprovação, que alcança todos os métodos.';
$string['bulkoutsidegroups'] = '{$a} das inscrições selecionadas estão fora dos seus grupos, ou são suas, e não serão decididas.';
$string['bulkoutsidegroupsone'] = '1 das inscrições selecionadas está fora dos seus grupos, ou é sua, e não será decidida.';
$string['bulkskipped'] = 'Usuários selecionados sem solicitação aguardando decisão, mantidos como estavam: {$a}';
$string['bulkunchanged'] = 'Solicitações cuja inscrição não mudou: {$a}';
$string['bulkwait'] = 'Adiar solicitações de inscrição';
$string['bulkwaitdesc'] = 'As solicitações listadas abaixo serão adiadas: elas continuam aguardando, e ninguém é inscrito nem tem a inscrição cancelada. Quem estiver na seleção sem aguardar decisão fica como está.';
$string['cachedef_keyattempts'] = 'Chaves de inscrição de grupo incorretas por solicitante';
$string['cancelmail_desc'] = '';
$string['cancelmail_heading'] = 'E-mail de cancelamento';
$string['cancelmailcontent'] = 'Conteúdo do e-mail de cancelamento';
$string['cancelmailcontent_default'] = '<p>Olá {firstname},</p><p>Sua solicitação de inscrição em {content} não foi aceita.</p>';
$string['cancelmailcontent_desc'] = 'Deixe em branco para usar o texto que este plugin já traz. Use as marcações a seguir para substituir o conteúdo do e-mail por dados do Moodle.<br/>{firstname}: o nome do usuário; {content}: o nome do curso; {lastname}: o sobrenome do usuário; {username}: o nome de usuário do cadastro';
$string['cancelmailsubject'] = 'Assunto do e-mail de cancelamento';
$string['cancelmailsubject_default'] = 'Sua solicitação de inscrição em {$a} não foi aceita';
$string['cancelmailsubject_desc'] = 'Deixe em branco para usar o texto que este plugin já traz.';
$string['canntenrolearly'] = 'Você ainda não pode solicitar inscrição; as solicitações abrem em {$a}.';
$string['canntenrollate'] = 'Você não pode mais solicitar inscrição, pois as solicitações se encerraram em {$a}.';
$string['cantenrol'] = 'A inscrição está desativada ou inativa';
$string['checkyourdetails'] = 'Confira seus dados';
$string['choicekey'] = 'E com uma chave de grupo?';
$string['choicekey_help'] = 'Se a chave de inscrição de grupo ainda vale ao lado da lista.

* **Não**: as chaves são ignoradas. Todos escolhem um grupo e aguardam uma decisão.
* **Uma chave válida inscreve na hora**: quem tem uma chave válida é inscrito na hora no grupo da chave, e o grupo escolhido é ignorado. Os demais escolhem e aguardam uma decisão.

Os modos só com chave não aparecem aqui: neles a chave decide o grupo, e uma lista só poderia contradizê-la.';
$string['chosenchip'] = 'Escolheu: {$a}';
$string['chosenchipunnamed'] = 'Escolheu um grupo';
$string['chosengroup'] = 'Grupo';
$string['chosengroup_help'] = 'Escolha o grupo para o qual você se candidata. A lista mostra os grupos que este método de inscrição oferece e, quando as vagas são contadas, quantas restam em cada um. Um grupo sem vagas não pode ser escolhido. Sua solicitação não ocupa vaga até ser aprovada.';
$string['chosengroupcolumn'] = 'Grupo escolhido';
$string['chosengroupcount'] = '{$a} grupos listados.';
$string['chosengroupcountone'] = '1 grupo listado.';
$string['chosengroupempty'] = 'Todos os grupos estão sem vagas. Digite um nome para vê-los.';
$string['chosengroupfull'] = 'Sem vagas';
$string['chosengroupmore'] = 'Mostrando {$a->shown} de {$a->available} grupos com vagas. Digite para buscar entre todos os {$a->all}.';
$string['chosengroupnomatch'] = 'Nenhum grupo corresponde a "{$a}".';
$string['chosengroupnone'] = 'Sem preferência';
$string['chosengroupnoplace'] = 'Este grupo não tem mais vagas. Escolha outro grupo.';
$string['chosengroupoption'] = '{$a->name} ({$a->status})';
$string['chosengroupplace'] = '1 vaga restante';
$string['chosengroupplaceholder'] = 'Escolha ou digite um grupo';
$string['chosengroupplaces'] = '{$a} vagas restantes';
$string['chosengrouprefine'] = 'Mais de {$a} grupos correspondem. Refine sua busca.';
$string['chosengrouprequired'] = 'Escolha um grupo.';
$string['chosengroupshow'] = 'Mostrar grupos';
$string['chosengroupunavailable'] = 'Este grupo não pode ser escolhido. Escolha um da lista.';
$string['cohortnonmemberinfo'] = 'A inscrição neste curso é restrita aos membros de um coorte específico. Se você acredita que deveria ter acesso, entre em contato com a administração do curso.';
$string['cohortonly'] = 'Somente membros do coorte';
$string['cohortonly_help'] = 'Restringe as solicitações aos membros de um coorte.

* Quem não é do coorte é informado de que não pode solicitar aqui, sem que o coorte seja nomeado.
* Alterar esta opção não afeta as solicitações já enviadas nem as inscrições existentes.

**Exemplo**: um método que recruta moderadores recebe solicitações apenas do coorte das pessoas convidadas.';
$string['cohortunresolved'] = 'Este método de inscrição está restrito a um coorte que não existe neste site, portanto nenhuma solicitação pode ser aceita. Entre em contato com a administração do curso.';
$string['comment'] = 'Comentário';
$string['conditionalways'] = 'Sempre exibida';
$string['conditionchecked'] = 'está marcada';
$string['conditioncontroller'] = 'Exibir esta pergunta somente quando esta pergunta anterior';
$string['conditionedit'] = 'Condição de exibição';
$string['conditioneditfor'] = 'Editar a condição de exibição de {$a}';
$string['conditionerror_controller'] = 'A pergunta escolhida para controlar esta não é feita por este método de inscrição.';
$string['conditionerror_field'] = 'Esta pergunta não é feita por este método de inscrição.';
$string['conditionerror_later'] = 'Somente uma pergunta feita antes no formulário pode controlar esta.';
$string['conditionerror_operator'] = 'Escolha se a caixa deve estar marcada ou desmarcada.';
$string['conditionerror_positions'] = 'Escolha pelo menos uma das opções listadas.';
$string['conditionerror_type'] = 'Somente um menu suspenso ou uma caixa de seleção pode controlar outra pergunta.';
$string['conditionnotchecked'] = 'não está marcada';
$string['conditionoperator'] = 'Quando "{$a}"';
$string['conditionpositions'] = 'Quando "{$a}" tem uma destas respostas';
$string['conditionquestion'] = 'Pergunta';
$string['conditionremove'] = 'Remover';
$string['conditionremoved'] = 'A condição de exibição foi removida: a pergunta é sempre exibida.';
$string['conditionremovefor'] = 'Remover a condição de exibição de {$a}';
$string['conditions'] = 'Condições de exibição';
$string['conditions_desc'] = 'Uma pergunta pode ser exibida somente quando um menu suspenso ou uma caixa de seleção anterior no formulário tem uma resposta determinada. Enquanto estiver oculta, ela não é obrigatória, mesmo que esteja configurada como obrigatória. Se ela ainda estiver oculta no momento do envio, o que foi digitado nela não é guardado, porque a pergunta não se aplica a essa inscrição. Se o solicitante voltar a exibi-la antes de enviar, o que ele digitou continua lá.';
$string['conditionsaved'] = 'A condição de exibição foi salva.';
$string['conditionsempty'] = 'Este método de inscrição ainda não faz nenhuma pergunta.';
$string['conditionsnone'] = 'Nenhum menu suspenso ou caixa de seleção é perguntado antes desta pergunta, então nada pode controlá-la. Uma pergunta só pode depender de outra feita antes: arraste o menu suspenso ou a caixa de seleção para cima desta pergunta no editor da página Campos da solicitação e volte aqui.';
$string['conditionstale'] = 'Sempre exibida: sua condição não se aplica mais às perguntas feitas';
$string['conditionsummary'] = 'Quando é exibida';
$string['conditionsummarychecked'] = 'Exibida quando "{$a}" está marcada';
$string['conditionsummaryin'] = 'Exibida quando a resposta a "{$a->field}" é uma de: {$a->options}';
$string['conditionsummarynotchecked'] = 'Exibida quando "{$a}" não está marcada';
$string['confirmalldetails'] = 'Estes dados estão atualizados';
$string['confirmenrol'] = 'Gerenciar solicitação';
$string['confirmfield'] = '\'{$a}\' está atualizado';
$string['confirmmail_desc'] = '';
$string['confirmmail_heading'] = 'E-mail de confirmação';
$string['confirmmailcontent'] = 'Conteúdo do e-mail de confirmação';
$string['confirmmailcontent_default'] = '<p>Olá {firstname},</p><p>Sua solicitação de inscrição em {content} foi aprovada. Você já pode entrar no curso.</p>';
$string['confirmmailcontent_desc'] = 'Deixe em branco para usar o texto que este plugin já traz. Use as marcações a seguir para substituir o conteúdo do e-mail por dados do Moodle.<br/>{firstname}: o nome do usuário; {content}: o nome do curso; {lastname}: o sobrenome do usuário; {username}: o nome de usuário do cadastro; {timeend}: a data de expiração da inscrição';
$string['confirmmailsubject'] = 'Assunto do e-mail de confirmação';
$string['confirmmailsubject_default'] = 'Sua solicitação de inscrição em {$a} foi aprovada';
$string['confirmmailsubject_desc'] = 'Deixe em branco para usar o texto que este plugin já traz.';
$string['confirmusers'] = 'Confirmar inscrições';
$string['confirmusersinstance'] = 'Confirmar inscrições: {$a}';
$string['coursegroupmode'] = 'Modalidade de grupo do curso';
$string['coursegroupmodelink'] = 'Alterar nas configurações do curso';
$string['coursegroupmodeseparate'] = 'Uma solicitação com grupo chega a quem decide por esse grupo e a quem vê todos os grupos.';
$string['coursegroupmodeshared'] = 'Todos que podem decidir veem todas as solicitações.';
$string['coursename'] = 'Curso';
$string['custom_label'] = 'Pergunta do comentário';
$string['custom_label_help'] = 'A pergunta que encabeça a caixa de comentário.

* A mesma redação encabeça a coluna de comentário da fila e o comentário na página de revisão, de modo que a pergunta feita e as respostas lidas coincidam.
* Deixe em branco para usar a redação padrão.

**Exemplo**: "Por que você quer fazer este curso?".';
$string['datasource:applications'] = 'Solicitações de inscrição';
$string['decideapplication'] = 'Decidir esta solicitação';
$string['decided'] = 'Decisão';
$string['decidedbykey'] = 'Pela chave: {$a}';
$string['decidedbykeyonly'] = 'Pela chave';
$string['decidedbyname'] = 'por {$a}';
$string['decidednotyet'] = 'Ainda não';
$string['deciders'] = 'Quem decide as solicitações deste método?';
$string['deciders_allgroups'] = 'Somente quem vê todos os grupos';
$string['deciders_group'] = 'Quem decide pelo grupo da solicitação';
$string['deciders_help'] = 'Só tem efeito em cursos com **grupos separados**. Nas outras modalidades, todos que podem decidir veem todas as solicitações.

* **Quem decide pelo grupo da solicitação**: a solicitação com grupo (escolhido, ou o da chave) chega aos moderadores daquele grupo e a quem vê todos os grupos. A solicitação sem grupo chega só a quem vê todos os grupos.
* **Somente quem vê todos os grupos**: nenhum moderador de grupo vê ou decide as solicitações deste método.

**Quando usar cada uma**

* Estudantes se inscrevem por turma, e cada moderador aprova a sua: a primeira opção.
* Recrutamento de moderadores (o papel na aprovação pode decidir solicitações): a segunda opção, para que um moderador nunca aprove outro.
* Aprovação centralizada na coordenação: a segunda opção.

**Estudantes e moderadores no mesmo curso**: crie dois métodos, um para cada papel. Quem solicitou por um método de moderadores não pode solicitar pelo de estudantes.';
$string['decidersallgroupsinfo'] = 'Somente quem vê todos os grupos decide as solicitações deste método. Quem decide por um grupo nunca as vê.';
$string['decidersinert'] = 'Este curso não está no modo de grupos separados: todos que podem decidir veem todas as solicitações, e esta opção não muda nada.';
$string['decisiongroupfull'] = '{$a->name} ({$a->members} de {$a->seats} vagas ocupadas, lotado)';
$string['decisiongroups'] = 'Grupos para ingressar na aprovação';
$string['decisiongroups_help'] = 'Os solicitantes aprovados entram nestes grupos. Deixe em branco para usar os grupos configurados no método de inscrição, mais o grupo que cada solicitante escolheu, quando escolheu um.';
$string['decisiongroupseats'] = '{$a->name} ({$a->members} de {$a->seats} vagas ocupadas)';
$string['decisiongroupsnone'] = 'Usar os grupos do método de inscrição';
$string['decisionnote'] = 'Anotação para o registro';
$string['decisionnote_help'] = 'Fica guardada com a solicitação para quem for olhá-la depois, e aparece no relatório de solicitações. O solicitante nunca a recebe - use "Mensagem ao solicitante" para o que ele deve ler. Deixar em branco apaga a anotação de uma decisão anterior, porque a anotação pertence à decisão que está sendo tomada.';
$string['decisionrole'] = 'Papel a atribuir na aprovação';
$string['decisionrole_help'] = 'Os solicitantes aprovados recebem este papel no curso. Deixe como está para usar o papel configurado no método de inscrição. Só são oferecidos os papéis que você pode atribuir neste curso, e apenas a aprovação usa esta escolha - adiar ou cancelar a ignora.';
$string['decisionroledefault'] = 'Usar o papel definido no método de inscrição';
$string['decisionsummary'] = 'O que uma aprovação concede';
$string['decisionsummary_help'] = 'Definido pelo método de inscrição: você pode aprovar ou recusar esta solicitação, mas não alterá-los.';
$string['decisionsummarybulk'] = 'Cada aprovação concede o que o seu método de inscrição define: os grupos dele (e o grupo da chave, para uma solicitação que uma chave de grupo enviou para análise, ou o grupo que o solicitante escolheu) e o papel dele. Você pode aprovar ou recusar, mas não alterá-los.';
$string['decisionsummarychosen'] = 'Uma solicitação feita com um grupo escolhido também entra nesse grupo.';
$string['decisionsummarygroups'] = 'Grupos em que uma aprovação inscreve';
$string['decisionsummaryhidden'] = 'mais {$a} que você não pode ver';
$string['decisionsummarykey'] = 'Uma solicitação que uma chave de grupo enviou para análise também entra no grupo da chave.';
$string['decisionsummaryrole'] = 'Papel que uma aprovação atribui';
$string['defaultperiod'] = 'Duração padrão da inscrição';
$string['defaultperiod_desc'] = 'Tempo padrão de validade da inscrição. Se for zero, a duração da inscrição será ilimitada por padrão.';
$string['defaultperiod_help'] = 'Quanto tempo dura uma inscrição aprovada, contado a partir do momento da aprovação.

* **Desativado**: a inscrição não tem fim.
* **Ativado**: ao fim do período, a inscrição expira e a vaga é liberada.

O tempo que a solicitação espera por uma decisão não conta.

**Exemplo**: 180 dias dá a cada solicitante aprovado seis meses de acesso, seja qual for a data da aprovação.';
$string['defaultrole_desc'] = 'Papel atribuído ao usuário quando a solicitação de inscrição é aprovada.';
$string['detailsthattravel'] = 'Dados que acompanham sua solicitação';
$string['detailsthattravel_desc'] = 'Estes dados são enviados a quem analisar sua solicitação. Editá-los aqui não altera o seu perfil.';
$string['detailsthattravel_desc_required'] = 'Estes dados ficam registrados com a sua inscrição. Alterá-los aqui não muda o seu perfil.';
$string['detailsthattravel_required'] = 'Seus dados';
$string['editdescription'] = 'Texto exibido aos solicitantes';
$string['editdescription_help'] = 'Exibido no alto do formulário de solicitação, acima das perguntas, a todos que o abrem.

* É HTML: links e formatação são mantidos.
* Deixe em branco para não exibir nada.

**Exemplo**: a quem o curso se destina, o que é avaliado na solicitação e quando sai a resposta.';
$string['editenrolmentrefused'] = 'Você não pode alterar esta inscrição: ela é uma solicitação aguardando decisão, e você não pode decidi-la.';
$string['enrolenddate'] = 'Encerramento das solicitações';
$string['enrolenddate_help'] = 'Se ativado, as solicitações só podem ser enviadas até esta data. As solicitações enviadas antes dela continuam na fila e podem ser decididas depois.

Não é o fim do acesso ao curso: quanto tempo dura uma inscrição aprovada é a "Duração padrão da inscrição".';
$string['enrolenddaterror'] = 'A data de encerramento das solicitações não pode ser anterior à data de abertura';
$string['enrolmentactive'] = 'Inscrito';
$string['enrolmentgone'] = 'Não está mais inscrito';
$string['enrolmentsuspended'] = 'Suspenso';
$string['enrolmentunknown'] = 'Desconhecido';
$string['enrolmentwaiting'] = 'Adiada';
$string['enrolstartdate'] = 'Abertura das solicitações';
$string['enrolstartdate_help'] = 'Se ativado, as solicitações só podem ser enviadas a partir desta data. Antes dela, o cartão do método informa quando as solicitações abrem.

Não é o início do acesso ao curso: a inscrição aprovada começa na aprovação e dura a "Duração padrão da inscrição".';
$string['enrolwithkey'] = 'Inscrever-se com chave';
$string['entity:answer'] = 'Resposta da solicitação';
$string['entity:submission'] = 'Solicitação de inscrição';
$string['eventapplicationadmittedbykey'] = 'Solicitante inscrito com chave de grupo';
$string['eventapplicationapproved'] = 'Solicitação de inscrição aprovada';
$string['eventapplicationcancelled'] = 'Solicitação de inscrição cancelada';
$string['eventapplicationdeferred'] = 'Solicitação de inscrição adiada';
$string['expiredaction'] = 'Ação na expiração da inscrição';
$string['expiredaction_help'] = 'Selecione a ação a ser executada quando a inscrição do usuário expirar. Observe que alguns dados e configurações do usuário são removidos do curso durante o cancelamento da inscrição.';
$string['expiry_desc'] = '';
$string['expiry_heading'] = 'Configurações de expiração';
$string['expirymessageenrolledbody'] = 'Prezado(a) {$a->user},

Esta é uma notificação de que sua inscrição no curso \'{$a->course}\' expira em {$a->timeend}.

Se precisar de ajuda, entre em contato com {$a->enroller}.';
$string['expirymessageenrolledsubject'] = 'Notificação de expiração de inscrição';
$string['expirymessageenrollerbody'] = 'As inscrições no curso \'{$a->course}\' expirarão nos próximos {$a->threshold} para os seguintes usuários:

    {$a->users}

Para estender as inscrições, acesse {$a->extendurl}';
$string['expirymessageenrollersubject'] = 'Notificação de expiração de inscrição';
$string['expirynotify'] = 'Avisar antes de a inscrição expirar';
$string['expirynotify_help'] = 'Se uma mensagem é enviada antes de uma inscrição expirar. Só vale quando a inscrição tem duração.

* **Não**: nenhuma mensagem.
* **Somente o professor**: o professor responsável pelo método recebe a lista das inscrições prestes a expirar.
* **Professor e usuário inscrito**: cada usuário inscrito também é avisado da própria inscrição.

Com quanta antecedência a mensagem sai é o "Limite para notificação".';
$string['expirynotifyall'] = 'Professor e usuário inscrito';
$string['expirynotifyenroller'] = 'Somente o professor';
$string['expirynotifyhour_desc'] = 'Hora do dia em que as notificações de expiração de inscrição são enviadas.';
$string['expirythreshold'] = 'Limite para notificação';
$string['expirythreshold_help'] = 'Com quanta antecedência do fim da inscrição a mensagem é enviada.

**Exemplo**: com 7 dias, quem tem a inscrição terminando no dia 30 é avisado no dia 23.';
$string['fieldrequired'] = 'Obrigatório';
$string['fieldtypenotallowed'] = 'Este tipo de pergunta não pode ser feito no formulário de solicitação.';
$string['gotocourse'] = 'Ir para o curso';
$string['gotoprofile'] = 'Ir para o meu perfil';
$string['group'] = 'Grupos atribuídos na aprovação';
$string['group_help'] = 'Todo solicitante aprovado entra nestes grupos, qualquer que seja o grupo que escolheu ou para o qual digitou uma chave.

* **Além do grupo do solicitante**: o grupo escolhido na lista, ou o grupo da chave, também é atribuído.
* **Na página de revisão**: quem decide e pode escolher a decisão pode mudar os grupos.
* **Removidos com a inscrição**: as participações pertencem a este método.

**Exemplo**: um método que recruta moderadores coloca todos num grupo de controle "Moderadores", e cada um entra também na turma que escolheu moderar.

**Mantenha o grupo de controle fora de toda lista**: num curso em modo de grupos separados, o moderador decide as solicitações de todos os grupos em que este método o colocou. Use um grupo que nenhum método oferece e que não tem chave de inscrição.';
$string['groupchoicerequired'] = 'O solicitante precisa escolher';
$string['groupchoicerequired_help'] = 'Se uma solicitação pode ser enviada sem grupo.

* **Sim**: nenhuma solicitação sem grupo.
* **Não**: a lista começa com "Sem preferência", e quem aprova coloca o solicitante num grupo.

Num curso em modo de grupos separados, uma solicitação com "Sem preferência" chega somente a quem vê todos os grupos.';
$string['groupchoicerequired_no'] = 'Não: pode responder "Sem preferência"';
$string['groupchoicerequired_yes'] = 'Sim: nenhuma solicitação sem grupo';
$string['groupdecided'] = 'Como o grupo do solicitante é decidido?';
$string['groupdecided_choice'] = 'O solicitante escolhe numa lista';
$string['groupdecided_decider'] = 'Quem decide escolhe na aprovação';
$string['groupdecided_help'] = 'Quem indica o grupo de uma solicitação.

* **Quem decide escolhe na aprovação**: o solicitante não indica grupo, e quem aprova escolhe os grupos em que ele entra.
* **O solicitante escolhe numa lista**: o método oferece grupos e o solicitante escolhe um ao solicitar. Quem aprova vê a escolha e pode alterá-la.
* **O solicitante digita uma chave de inscrição de grupo**: a chave definida na página do grupo decide o grupo. O modo da chave diz se ela inscreve na hora ou envia a solicitação para análise.

**Quando usar cada uma**

* Sem turmas, ou a coordenação distribui as pessoas: quem decide escolhe.
* Turmas que o solicitante conhece, como uma cidade ou um turno: a lista.
* Turmas fechadas de antemão, com a chave entregue por cada professor: a chave.

Num curso em modo de grupos separados, uma solicitação com grupo chega a quem decide por esse grupo.';
$string['groupdecided_key'] = 'O solicitante digita uma chave de inscrição de grupo';
$string['groupkey'] = 'Chave de inscrição do grupo';
$string['groupkey_help'] = 'Se o seu professor lhe deu uma chave do seu grupo, informe-a para ser inscrito imediatamente e entrar nesse grupo. Sem a chave, sua inscrição passa por análise. A chave é opcional.';
$string['groupkeyadmitsnobody'] = 'Lotado: a chave não admite ninguém agora';
$string['groupkeyblock_any'] = 'Tem a chave do seu grupo? Digite-a para entrar na hora. Sem ela, sua inscrição passa por análise.';
$string['groupkeyblock_required'] = 'Digite a chave que seu professor forneceu, exatamente como recebeu. Ela define o seu grupo.';
$string['groupkeyblock_review'] = 'Digite a chave que seu professor forneceu, exatamente como recebeu. Sua inscrição passa então por análise.';
$string['groupkeyinvalid'] = 'Esta chave não é válida para este curso. Confira e tente novamente, ou envie sua inscrição para análise abaixo.';
$string['groupkeyinvalid_required'] = 'Esta chave não é válida para este curso. Confira e tente de novo.';
$string['groupkeylocked'] = 'Muitas chaves incorretas. Tente novamente em alguns minutos ou envie sua inscrição para análise agora.';
$string['groupkeylocked_required'] = 'Muitas chaves incorretas. Tente de novo em alguns minutos ou fale com quem lhe passou a chave.';
$string['groupkeyoffered'] = 'Tem uma chave de inscrição do grupo? Informe-a no formulário para entrar imediatamente, sem análise.';
$string['groupkeyonly'] = 'Chave de inscrição do grupo';
$string['groupkeyonly_help'] = 'Digite a chave que seu professor forneceu para o seu grupo, exatamente como recebeu. Ela inscreve você na hora e define o seu grupo. Sem uma chave válida não é possível se inscrever por este método.';
$string['groupkeyrequired'] = 'Digite a chave de inscrição do grupo para se inscrever.';
$string['groupkeyrequired_review'] = 'Digite a chave de inscrição do grupo para enviar a solicitação.';
$string['groupkeyreview'] = 'Chave de inscrição do grupo';
$string['groupkeyreview_help'] = 'Digite a chave que seu professor forneceu para o seu grupo, exatamente como recebeu. Ela envia sua inscrição para análise e indica o seu grupo. Sem uma chave válida não é possível solicitar inscrição por este método.';
$string['groupkeys'] = 'O que a chave de inscrição do grupo faz';
$string['groupkeys_any'] = 'Uma chave válida inscreve na hora; sem ela, a inscrição passa por análise';
$string['groupkeys_desc'] = 'O que a chave de inscrição de um grupo do curso faz num novo método de inscrição: nada, inscrever na hora enquanto as demais inscrições passam por análise, inscrever na hora sem que ninguém consiga se inscrever sem ela, ou enviar a inscrição para análise sem que ninguém consiga se inscrever sem ela.';
$string['groupkeys_help'] = 'As chaves são definidas na página de configurações de cada grupo por quem pode gerenciar grupos, e vale a chave de qualquer grupo do curso.

* **Uma chave válida inscreve na hora; sem ela, a inscrição passa por análise**: o solicitante que digita uma chave válida é inscrito imediatamente e entra nesse grupo. Sem chave, com uma chave errada ou sem vaga, a solicitação aguarda análise.
* **Só com chave**: uma chave válida inscreve na hora, e ninguém se inscreve por este método sem ela. Nada fica aguardando decisão: sem uma chave válida, ou sem vaga, a inscrição é recusada e nada é guardado.
* **Só com chave, com análise**: uma chave válida envia a solicitação para análise, e ninguém solicita sem ela. O grupo da chave fica registrado com a solicitação, e o solicitante entra nele na aprovação.

**Quando usar cada uma**

* O professor entrega a chave à turma, e os atrasados pedem uma vaga: o primeiro modo.
* Só entra quem tem uma chave, sem análise: só com chave.
* A chave diz qual é a turma, e a coordenação ainda aprova cada pessoa: só com chave, com análise.

Em qualquer modo os dados obrigatórios e as perguntas continuam sendo pedidos, e o período de solicitação, a restrição por coorte, o limite de solicitações e as vagas continuam valendo.';
$string['groupkeys_off'] = 'Nada: toda inscrição passa por análise';
$string['groupkeys_required'] = 'Só com chave: uma chave válida inscreve na hora, e ninguém se inscreve sem ela';
$string['groupkeys_review'] = 'Só com chave: uma chave válida envia a inscrição para análise';
$string['groupkeysattention'] = '{$a} deles pedem sua atenção e aparecem aqui.';
$string['groupkeysattentionone'] = 'Um deles pede sua atenção e aparece aqui.';
$string['groupkeyscount'] = '{$a} grupos têm chave de inscrição.';
$string['groupkeyselfkeys'] = 'Este curso também tem autoinscrição com chaves de grupo: as mesmas chaves inscrevem lá sem estas perguntas.';
$string['groupkeyshadowed'] = 'Mesma chave de {$a}, que é quem admite.';
$string['groupkeysingroups'] = 'Grupos com chave de inscrição';
$string['groupkeysmanage'] = 'Gerenciar grupos e suas chaves';
$string['groupkeysnone'] = 'Nenhum grupo deste curso tem chave de inscrição ainda, então todas as inscrições continuam passando por análise.';
$string['groupkeysshowall'] = 'Ver os {$a} grupos';
$string['groupkeyuntypable'] = 'A chave começa ou termina com espaço, que ninguém consegue digitar no formulário de inscrição.';
$string['groupkeyunusualspace'] = 'Sua chave começa ou termina com um espaço incomum, como costuma acontecer com uma chave copiada. Digite-a de novo, ou cole sem o espaço.';
$string['groupoffer'] = 'Grupos oferecidos';
$string['groupoffer_help'] = 'Os grupos entre os quais o solicitante pode escolher.

* Um grupo que só seus membros veem nunca é oferecido, embora continue nesta lista.
* Onde as vagas dos grupos valem, um grupo lotado aparece, mas não pode ser escolhido.
* A solicitação não ocupa vaga até ser aprovada.

**Não ofereça um grupo de controle**: um grupo que um método de recrutamento deste curso atribui na aprovação colocaria todas as solicitações feitas a ele diante de todas as pessoas que aquele método aprovou.';
$string['groupofferempty'] = 'Ofereça ao menos um grupo: o solicitante precisa escolher um.';
$string['groupoffergrouping'] = 'Adicionar os grupos de um agrupamento';
$string['groupoffergrouping_help'] = 'Adiciona todos os grupos do agrupamento aos grupos oferecidos ao salvar.

* Os grupos são copiados uma vez, naquele momento.
* Um grupo adicionado depois ao agrupamento só é oferecido quando você o adicionar aqui.

**Exemplo**: o curso tem um agrupamento "Turmas 2026" com doze grupos. Escolha-o aqui em vez de selecionar os doze um a um.';
$string['groupofferhidden'] = '{$a} (nunca oferecido: oculto para quem não é membro)';
$string['groupscopekeyless'] = 'As solicitações sem chave chegam apenas a quem pode acessar todos os grupos.';
$string['groupscopenopreference'] = 'As solicitações com "Sem preferência" chegam apenas a quem pode acessar todos os grupos.';
$string['groupseatsexceeded'] = 'O grupo {$a->group} agora tem {$a->members} membros para {$a->seats} vagas.';
$string['groupseatsnolimit'] = '{$a} membros, sem limite';
$string['groupseatsof'] = '{$a->members} de {$a->seats} vagas ocupadas';
$string['groupseatsoption'] = 'As vagas dos grupos valem para este método';
$string['groupseatsoption_help'] = 'Com o local_groupdist, um grupo pode declarar vagas, e cada membro ocupa uma, qualquer que seja o seu papel.

* **Sim**: a lista mostra as vagas restantes de cada grupo e recusa um grupo lotado.
* **Não**: este método não mostra nem confere vagas.

Nos dois casos a aprovação continua adicionando o membro, e o aviso de vagas depois dela continua apontando um grupo acima das suas vagas.

**Exemplo**: escolha Não num método que recruta moderadores, que não devem ocupar a vaga de um estudante.';
$string['groupseatsoption_no'] = 'Não: ignorar as vagas';
$string['groupseatsoption_yes'] = 'Sim: mostrar as vagas restantes e recusar um grupo lotado';
$string['groupselectchoice'] = 'O solicitante também entra no grupo que escolheu.';
$string['groupselectkey'] = 'O solicitante também entra no grupo da chave.';
$string['hidecard'] = 'Ocultar este cartão de quem não é do coorte';
$string['hidecard_help'] = 'O que as pessoas de fora do coorte veem na página de inscrição do curso.

* **Não**: todos veem o cartão deste método, e quem não é do coorte é informado de que não pode solicitar.
* **Sim**: essas pessoas nem veem o cartão. Quem já solicitou sempre vê o seu.

**Exemplo**: num método que recruta moderadores, escolha Sim. Os convidados chegam a ele pelo coorte, ou pelo link para o formulário do próprio método que a coordenação lhes envia, e os estudantes nunca o veem.';
$string['hidecard_no'] = 'Não: mostrar, dizendo que o método é restrito';
$string['hidecard_yes'] = 'Sim: só membros da coorte veem este método';
$string['invalidformaction'] = 'Ação desconhecida solicitada para as inscrições selecionadas.';
$string['joinedgroups'] = 'Grupos em que entrou';
$string['keychipgroup'] = '{$a->outcome}: {$a->group}';
$string['keygroup'] = 'Grupo da chave de inscrição';
$string['keygroupfull'] = 'Sua chave do grupo {$a} é válida, mas o grupo não tem mais vagas.';
$string['keygroupgone'] = 'Um grupo excluído depois';
$string['keyjoins'] = 'Você vai entrar no grupo {$a}.';
$string['keyjoinsforreview'] = 'Sua inscrição no grupo {$a} será analisada.';
$string['keynoplace'] = 'Sua chave é válida, mas o curso não tem mais vagas.';
$string['keyonlyallfull'] = 'Todos os grupos com chave estão lotados, então ninguém consegue se inscrever por este método agora.';
$string['keyonlycard'] = 'Este curso exige a chave de inscrição do grupo que seu professor forneceu.';
$string['keyonlynokeys'] = 'Nenhum grupo deste curso tem chave de inscrição, então ninguém consegue se inscrever por este método.';
$string['keyonlypending'] = '{$a} inscrições ainda aguardam decisão. Mudar para só com chave não as decide; elas continuam na fila.';
$string['keyonlyqueue'] = 'Este método inscreve só por chave de grupo; nada aguarda decisão.';
$string['keyoutcomeadmitted'] = 'Admitido com chave de grupo, sem análise';
$string['keyoutcomeforreview'] = 'Chave aceita, enviada para análise';
$string['keyoutcomeforreviewshort'] = 'Chave aceita';
$string['keyoutcomegroupfull'] = 'Chave de grupo válida, mas um grupo não tinha vaga';
$string['keyoutcomegroupfullshort'] = 'Chave: sem vaga no grupo';
$string['keyoutcomenoplace'] = 'Chave de grupo válida, mas não havia vaga';
$string['keyoutcomenoplaceshort'] = 'Chave: sem vaga';
$string['keyoutcomeseatunconfirmed'] = 'Chave de grupo válida, mas a vaga não pôde ser confirmada a tempo';
$string['keyoutcomeseatunconfirmedshort'] = 'Chave: não confirmada';
$string['keyreviewallfull'] = 'Todos os grupos com chave estão lotados, então ninguém consegue enviar solicitação por este método agora.';
$string['keyreviewnokeys'] = 'Nenhum grupo deste curso tem chave de inscrição, então ninguém consegue enviar solicitação por este método.';
$string['keyreviewpending'] = '{$a} solicitações sem chave ainda aguardam decisão; elas continuam na fila.';
$string['keyseatsfull'] = 'Sua chave é válida, mas um grupo desta inscrição não tem mais vagas.';
$string['keyseatunconfirmed'] = 'Sua chave é válida, mas não foi possível confirmar a vaga agora. Tente de novo.';
$string['limitnegative'] = 'Este limite não pode ser negativo. Informe 0 para não haver limite.';
$string['lockedby'] = 'Dados definidos pela sua instituição';
$string['mailtoteacher_admitted'] = 'Inscrito com a chave de inscrição do grupo {$a}, sem análise. Nada aguarda decisão.';
$string['mailtoteacher_admitted_subject'] = 'Inscrição com chave de grupo';
$string['mailtoteacher_admittednogroup'] = 'Inscrito com uma chave de inscrição de grupo, sem análise. Nada aguarda decisão.';
$string['mailtoteacher_chosengroup'] = 'O solicitante escolheu o grupo {$a}.';
$string['mailtoteacher_chosengroupunnamed'] = 'O solicitante escolheu um grupo.';
$string['mailtoteacher_keyreview'] = 'Solicitação feita com a chave de inscrição do grupo {$a}. A inscrição aguarda análise.';
$string['mailtoteacher_keyreviewnogroup'] = 'Solicitação feita com uma chave de inscrição de grupo. A inscrição aguarda análise.';
$string['mailtoteacher_subject'] = 'Nova solicitação de inscrição!';
$string['maxapplicants'] = 'Número máximo de solicitações';
$string['maxapplicants_help'] = 'O maior número de solicitações que este método recebe. 0 significa sem limite.

* **O que conta**: solicitações pendentes, adiadas e aprovadas. Uma inscrição cujo período terminou não conta, então este número difere da coluna Usuários da página de métodos de inscrição.
* **Ao ser atingido**: os solicitantes são informados apenas de que as solicitações estão encerradas. Eles nunca veem o número.
* **Uma solicitação adiada continua contando** até ser aprovada ou cancelada.

**Exemplo**: 30 aqui e 10 em "Vagas" recebe trinta solicitações para escolher dez pessoas.

Num método que inscreve só com chave de grupo, toda inscrição é aprovada na hora, então este número e as vagas contam quase as mesmas pessoas, e vale o menor.';
$string['maxenrolled'] = 'Número máximo de usuários inscritos';
$string['maxenrolled_help'] = 'Define o número máximo de usuários que podem solicitar inscrição neste curso. 0 significa sem limite.';
$string['maxenrolledreached'] = 'Não estamos mais aceitando solicitações de inscrição.';
$string['messageprovider:application'] = 'Notificações de solicitação de inscrição em curso';
$string['messageprovider:cancelation'] = 'Notificações de cancelamento de solicitação de inscrição';
$string['messageprovider:confirmation'] = 'Notificações de confirmação de solicitação de inscrição';
$string['messageprovider:expiry_notification'] = 'Notificações de expiração de inscrição';
$string['messageprovider:waitinglist'] = 'Notificações de adiamento de solicitação de inscrição';
$string['newapplicationnotification'] = 'Há uma nova solicitação de inscrição aguardando análise.';
$string['newenrols'] = 'Aceitar novas solicitações';
$string['newenrols_desc'] = 'Permitir, por padrão, que usuários solicitem inscrição em novas instâncias.';
$string['newenrols_help'] = 'Se é possível enviar uma nova solicitação por este método.

* **Sim**: o cartão do método na página de inscrição recebe solicitações.
* **Não**: ninguém consegue solicitar. As solicitações já enviadas continuam na fila e ainda podem ser decididas, e as inscrições aprovadas não são afetadas.

**Exemplo**: a turma fechou, mas ainda há solicitações aguardando. Coloque Não e termine de decidi-las.';
$string['nocohortsavailable'] = 'Não há coorte disponível para restringir este método.';
$string['nofieldsoffered'] = 'No momento a administração não permite coletar nenhum campo de perfil junto com a solicitação.';
$string['nothingtoprovide'] = 'Não há nada a preencher. Envie para solicitar inscrição em {$a}.';
$string['notification'] = 'Solicitação de inscrição enviada com sucesso. Você será notificado por e-mail quando a sua inscrição for confirmada.';
$string['notify'] = 'Avisar sobre novas solicitações';
$string['notify_desc'] = 'Defina quem é notificado sobre novas solicitações de inscrição.';
$string['notify_heading'] = 'Configurações de notificação';
$string['notify_help'] = 'Quem recebe uma mensagem quando alguém solicita por este método.

* **Ninguém**: nenhuma mensagem deste método.
* **Todos que podem "Gerenciar solicitações de inscrição"**: quem tem a capacidade no curso, com inscrição ativa, no momento em que a solicitação chega. Um moderador que entra depois é incluído sem editar este método.
* **Quem decide pelo grupo da solicitação**: só os moderadores daquele grupo. Quando a solicitação não tem grupo, ou o grupo não tem moderador, quem vê todos os grupos é avisado no lugar, de modo que nenhuma solicitação fica sem aviso.
* **Pessoas escolhidas pelo nome**: uma lista fixa, que pode ser combinada com a opção acima. Cada uma só é avisada enquanto ainda tiver a capacidade.

**Quando usar cada uma**

* Cada turma tem seus moderadores, e a coordenação não quer uma mensagem por turma: quem decide pelo grupo da solicitação.
* Um curso pequeno em que todos acompanham todas as solicitações: todos.
* O coordenador quer todas as solicitações, além dos moderadores: quem decide pelo grupo, mais o coordenador pelo nome.

Ninguém é avisado de uma solicitação que não pode decidir: num curso em modo de grupos separados, o moderador só recebe as dos próprios grupos. Os mentores do solicitante e as pessoas que a administração escolheu para o site todo também são avisados.';
$string['notifyapprovaltask'] = 'Envio de notificação de aprovação de inscrição';
$string['notifycoursebased'] = 'Notificação de nova solicitação de inscrição (por instância, ex.: professores do curso)';
$string['notifycoursebased_desc'] = 'Padrão para novas instâncias: notificar todos que possuem a capacidade \'Gerenciar solicitações de inscrição\' no curso correspondente (ex.: professores e gerentes)';
$string['notifyglobal'] = 'Notificação de nova solicitação de inscrição (global, ex.: gerentes e administradores)';
$string['notifyglobal_desc'] = 'Defina quem é notificado sobre novas solicitações de inscrição em qualquer curso.';
$string['notifygroupdeciders'] = 'Quem decide pelo grupo da solicitação';
$string['opt_commentaryzone'] = 'Pedir um comentário';
$string['opt_commentaryzone_help'] = 'Acrescenta uma caixa de texto livre ao formulário de solicitação.

* Quem decide lê o comentário na fila de solicitações e na página de revisão.
* A caixa é encabeçada pela "Pergunta do comentário", abaixo.

**Exemplo**: pergunte por que o solicitante quer fazer o curso. Para respostas com formato definido, use as perguntas próprias do método.';
$string['outcomeapproved'] = 'Aprovado, inscrito';
$string['outcomeawaiting'] = 'Aguardando decisão';
$string['outcomecancelled'] = 'Cancelado';
$string['outcomeexpired'] = 'Aprovado e depois expirado';
$string['outcomelabelexpired'] = 'Expirada';
$string['outcomelabelkey'] = 'Admitida com chave';
$string['outcomelabellapsed'] = 'Vencida';
$string['outcomelabelunenrolled'] = 'Sem inscrição';
$string['outcomelapsed'] = 'Expirou sem decisão';
$string['outcomemessage'] = 'Mensagem ao solicitante';
$string['outcomemessage_help'] = 'Incluída na mensagem que o solicitante recebe com a sua decisão. Deixe em branco para enviar apenas o texto padrão.';
$string['outcomeneverdecided'] = 'Nunca decidido e não está mais inscrito';
$string['outcomenowexpired'] = '{$a}, agora expirada';
$string['outcomenowlapsed'] = '{$a}, agora vencida';
$string['outcomenowsuspended'] = '{$a}, agora suspensa';
$string['outcomenowunenrolled'] = '{$a}, agora sem inscrição';
$string['outcomesuspended'] = 'Aprovado e depois suspenso';
$string['outcomeunenrolled'] = 'Aprovado e depois teve a inscrição removida';
$string['outcomewaiting'] = 'Adiada';
$string['ownapplicationnotdecidable'] = 'Sua própria solicitação: outra pessoa que decide solicitações deve decidi-la.';
$string['places'] = 'Vagas';
$string['places_help'] = 'Quantos solicitantes este método pode ter aprovados ao mesmo tempo. 0 significa sem limite.

* **O que conta**: apenas inscrições aprovadas. Uma inscrição cujo período terminou libera a vaga.
* **Ao ser atingido**: a aprovação não é bloqueada. Quem decide é avisado e decide.
* **Com chave de grupo que inscreve na hora**: uma chave válida é recusada quando não há mais vagas.

**Exemplo**: um método pode receber mais solicitações do que tem vagas, e essa é a intenção, já que nem todo solicitante é aprovado. Limite as solicitações em "Número máximo de solicitações".';
$string['placesfull'] = 'Todas as {$a} vagas deste método de inscrição estão ocupadas.';
$string['placestaken'] = '{$a->taken} de {$a->limit}';
$string['placestakenlabel'] = 'Vagas ocupadas';
$string['placestakennolimit'] = '{$a}, sem limite';
$string['pluginname'] = 'Inscrição mediante aprovação';
$string['pluginname_desc'] = 'Com este plugin os usuários podem solicitar inscrição em um curso. Um professor ou gerente do site precisa aprovar a solicitação antes de o usuário ser inscrito.';
$string['pluginname_keyonly'] = 'Inscrição com chave de grupo';
$string['pluginname_keyreview'] = 'Inscrição com chave de grupo e análise';
$string['presetchosen'] = 'Escolhido';
$string['presetclasses'] = 'Turmas com moderadores';
$string['presetclasseswhen'] = 'Quando o solicitante escolhe a sua turma e cada turma tem moderadores que decidem as suas solicitações.';
$string['presetclear'] = 'Começar dos padrões do site';
$string['presetkey'] = 'Chave de inscrição do grupo';
$string['presetkeywhen'] = 'Quando cada grupo distribui a sua própria chave de inscrição, e uma chave válida deve inscrever na hora.';
$string['presetmoderators'] = 'Recrutamento de moderadores';
$string['presetmoderatorsnote'] = 'Crie um segundo método para os estudantes: quem fez uma solicitação por este não pode fazer outra como estudante neste curso.';
$string['presetmoderatorswhen'] = 'Quando este método recruta as pessoas que vão moderar os grupos e decidir as solicitações deles.';
$string['presetneedsseparate'] = 'Este cenário precisa de grupos separados.';
$string['presetreview'] = 'Análise simples';
$string['presetreviewwhen'] = 'Quando quem decide lê cada solicitação e coloca cada solicitante aprovado num grupo.';
$string['presetrolemissing'] = 'Escolha um papel que decide solicitações';
$string['presets'] = 'Começar de um cenário';
$string['presetsintro'] = 'Um cenário só preenche o formulário abaixo: tudo ainda pode ser alterado antes de salvar.';
$string['presetuse'] = 'Usar este cenário';
$string['privacy:applicationpath'] = 'Solicitação de inscrição';
$string['privacy:metadata:core_group'] = 'Uma aprovação adiciona o solicitante aos grupos escolhidos para o método de inscrição ou por quem decidiu; o plugin exporta e exclui as participações em grupo que adicionou.';
$string['privacy:metadata:core_message'] = 'Uma aprovação adiciona o solicitante a grupos, e o plugin exporta e exclui as conversas dos grupos cujas participações adicionou.';
$string['privacy:metadata:enrol_apply_answer'] = 'O que o usuário enviou com uma solicitação de inscrição no curso, um valor por campo de perfil e por pergunta feita, como estava no envio.';
$string['privacy:metadata:enrol_apply_answer:fieldkey'] = 'A qual campo de perfil ou pergunta da solicitação o valor responde.';
$string['privacy:metadata:enrol_apply_answer:label'] = 'A pergunta como estava redigida quando a solicitação foi enviada.';
$string['privacy:metadata:enrol_apply_answer:submissionid'] = 'A solicitação com a qual o valor foi enviado, que identifica o usuário solicitante.';
$string['privacy:metadata:enrol_apply_answer:value'] = 'O valor que o usuário enviou.';
$string['privacy:metadata:enrol_apply_applicationinfo'] = 'Informações enviadas junto com uma solicitação de inscrição em curso.';
$string['privacy:metadata:enrol_apply_applicationinfo:comment'] = 'O comentário escrito pelo usuário ao solicitar a inscrição no curso.';
$string['privacy:metadata:enrol_apply_applicationinfo:userenrolmentid'] = 'A inscrição à qual a solicitação pertence, que identifica o usuário solicitante e o curso.';
$string['privacy:metadata:enrol_apply_submission'] = 'O registro durável de uma solicitação de inscrição no curso: o que foi enviado, o que foi decidido, por quem e quando.';
$string['privacy:metadata:enrol_apply_submission:chosengroupid'] = 'O grupo que o solicitante escolheu entre os oferecidos pelo método de inscrição.';
$string['privacy:metadata:enrol_apply_submission:comment'] = 'O comentário que o usuário escreveu ao solicitar a inscrição no curso.';
$string['privacy:metadata:enrol_apply_submission:courseid'] = 'O curso ao qual a solicitação foi enviada.';
$string['privacy:metadata:enrol_apply_submission:decidedby'] = 'O usuário que aprovou, adiou ou cancelou a solicitação.';
$string['privacy:metadata:enrol_apply_submission:decidedgroups'] = 'Os grupos que o responsável pela decisão escolheu para o solicitante entrar na aprovação.';
$string['privacy:metadata:enrol_apply_submission:decidedrole'] = 'O papel que o responsável pela decisão escolheu para o solicitante ter na aprovação.';
$string['privacy:metadata:enrol_apply_submission:decisionnote'] = 'A anotação que o decisor registrou sobre a decisão, que nunca é enviada ao solicitante.';
$string['privacy:metadata:enrol_apply_submission:enrolid'] = 'O método de inscrição ao qual a solicitação foi enviada.';
$string['privacy:metadata:enrol_apply_submission:joinedgroups'] = 'Os grupos de que o solicitante era membro por este método de inscrição depois da última aprovação.';
$string['privacy:metadata:enrol_apply_submission:keygroupid'] = 'O grupo cuja chave de inscrição o solicitante informou.';
$string['privacy:metadata:enrol_apply_submission:keyoutcome'] = 'O que essa chave de inscrição de grupo fez: admitiu o solicitante sem análise, enviou a inscrição para análise, ou deixou a inscrição aguardando porque não havia vaga.';
$string['privacy:metadata:enrol_apply_submission:outcomemessage'] = 'A mensagem que o responsável pela decisão escreveu ao solicitante.';
$string['privacy:metadata:enrol_apply_submission:status'] = 'Se a solicitação está pendente, aprovada, adiada ou cancelada.';
$string['privacy:metadata:enrol_apply_submission:timecreated'] = 'A data e hora em que a solicitação foi enviada.';
$string['privacy:metadata:enrol_apply_submission:timedecided'] = 'A data e hora em que a solicitação foi decidida.';
$string['privacy:metadata:enrol_apply_submission:timefirstdecided'] = 'A data e hora da primeira decisão sobre a solicitação, mantida quando uma decisão posterior é tomada.';
$string['privacy:metadata:enrol_apply_submission:userenrolmentid'] = 'A inscrição para a qual a solicitação foi enviada, quando ela ainda existe.';
$string['privacy:metadata:enrol_apply_submission:userid'] = 'O usuário que enviou a solicitação.';
$string['privacy:metadata:preference:queuegrid'] = 'As colunas que o usuário escolheu para a fila de aprovação de inscrições, e as larguras que deu a elas.';
$string['privacy:metadata:preference:reportcolumns'] = 'As colunas que o usuário escolheu para o relatório de inscrições do curso, e as larguras que deu a elas.';
$string['privacy:methodpath'] = 'Método de inscrição {$a}';
$string['privacy:recordpath'] = 'Registro de solicitação {$a}';
$string['privacy:request:preference:queuegrid'] = 'As colunas escolhidas para a fila de aprovação de inscrições, e suas larguras.';
$string['privacy:request:preference:reportcolumns'] = 'As colunas escolhidas para o relatório de inscrições do curso, e suas larguras.';
$string['privacy:roleapplicant'] = 'Solicitações que você enviou';
$string['privacy:roledecider'] = 'Solicitações que você decidiu';
$string['privacy:trailpath'] = 'Registros de solicitações de inscrição';
$string['profileincomplete'] = 'Faltam alguns dados no seu perfil';
$string['profileincomplete_desc'] = 'Sua solicitação foi enviada. Estes dados ainda não constam do seu perfil, e este site não permite que cursos os preencham por você.';
$string['profilenotupdated'] = 'Não havia nada a salvar no seu perfil.';
$string['profilenow'] = 'Seu perfil hoje';
$string['profileupdated'] = 'Seu perfil foi atualizado.';
$string['purgesubmissionstask'] = 'Excluir registros de solicitação de inscrição fora do prazo de retenção';
$string['questions'] = 'Perguntas';
$string['questionsmany'] = 'Este método faz {$a->count} perguntas próprias; "{$a->button}", no fim deste formulário, leva até elas.';
$string['questionsmanyconditional'] = 'Este método faz {$a->count} perguntas próprias, {$a->conditional} delas exibidas só sob uma condição; "{$a->button}", no fim deste formulário, leva até elas.';
$string['questionsnew'] = 'As perguntas próprias do método são criadas depois que ele é salvo: use "{$a}" no fim deste formulário.';
$string['questionsnone'] = 'Este método não faz perguntas próprias; "{$a}", no fim deste formulário, permite criá-las.';
$string['questionsone'] = 'Este método faz 1 pergunta própria; "{$a}", no fim deste formulário, leva até ela.';
$string['queueapplicationsclosed'] = 'Fechado';
$string['queueapplicationsopen'] = 'Aberto';
$string['queueappliedbefore'] = 'Já se inscreveu antes';
$string['queueawaiting'] = 'Aguardando decisão';
$string['queueclearfilters'] = 'Limpar tudo';
$string['queuecloseson'] = 'fecha em {$a}';
$string['queuedecisionoptions'] = 'Opções da decisão: aplicadas a cada solicitação que você decidir';
$string['queuedecisionspans'] = 'Uma só mensagem chega a cada solicitação que você decidir. Decida as solicitações de cada método separadamente para escrever mensagens diferentes.';
$string['queuedeferred'] = 'Adiadas para a lista de espera';
$string['queuefilteranycategory'] = 'Qualquer categoria';
$string['queuefilteranycourse'] = 'Qualquer curso';
$string['queuefiltercategory'] = 'Categoria do curso';
$string['queuefiltercount'] = '{$a->matched} de {$a->total} solicitações';
$string['queuefiltercourse'] = 'Curso';
$string['queuefilterempty'] = 'Nenhuma solicitação corresponde aos filtros aplicados. Limpe-os para ver a fila inteira.';
$string['queuefilterfields'] = 'Campos de perfil pelos quais a fila de solicitações pode ser filtrada';
$string['queuefilterfields_desc'] = 'Acrescenta à fila de aprovação um controle de filtro para cada campo marcado aqui. Três coisas valem saber. Os filtros leem o <em>perfil atual</em> do solicitante, e não os dados enviados com a solicitação - esses ficam congelados por solicitação e não podem ser filtrados. Um campo só aparece para quem já pode vê-lo naquela fila, então marcar uma caixa não concede nada a ninguém. E a linha de filtros quebra a partir de uns três controles, então uma seleção curta se lê melhor que uma completa. A lista oferece exatamente os campos que este site nomeia em Administração do site / Usuários / Políticas do usuário / Mostrar identidade do usuário.';
$string['queuefilterfrom'] = 'Solicitadas a partir de';
$string['queuefilternoidentity'] = 'Nenhum campo de identidade está configurado neste site, então não há o que a fila possa oferecer como filtro. Defina Mostrar identidade do usuário em Administração do site / Usuários / Políticas do usuário primeiro.';
$string['queuefilters'] = 'Filtros';
$string['queuefiltersgroup'] = 'Restringir a fila';
$string['queuefilterstatus'] = 'Situação da solicitação';
$string['queuefilterto'] = 'Solicitadas até';
$string['queuegrantsdecidingrole'] = 'Concede um papel que decide';
$string['queuegroup'] = 'Grupo';
$string['queuegroupany'] = 'Qualquer grupo';
$string['queuegroupmine'] = 'Meus grupos';
$string['queuegroupnone'] = 'Sem grupo';
$string['queuehistory'] = 'Solicitações anteriores';
$string['queueinyourgroups'] = 'nos seus grupos';
$string['queuenamed'] = 'Como o grupo foi indicado';
$string['queuenamedchosen'] = 'Escolhido da lista';
$string['queuenamedkey'] = 'Chave do grupo';
$string['queuenamednone'] = 'Não indicado';
$string['queueonthismethod'] = 'neste método';
$string['queueownapplication'] = 'Sua';
$string['queueplacestaken'] = 'Vagas ocupadas (aprovadas)';
$string['queueremaining'] = 'Mais {$a} solicitações serão aceitas antes de o método se fechar sozinho.';
$string['queueremovefilter'] = 'Remover o filtro {$a->name}: {$a->value}';
$string['queuereview'] = 'Analisar';
$string['queuereviewapplicant'] = 'Analisar a solicitação de {$a}';
$string['queuescopegroups'] = 'Você vê as inscrições de: {$a}';
$string['queuesearch'] = 'Buscar';
$string['queuesearch_help'] = 'Restringe a fila às solicitações cujo nome do solicitante, campos de identidade ou comentário contenham o que você digitar. Ela NÃO alcança os dados enviados com a solicitação: esses ficam congelados por solicitação e são mostrados apenas a quem tem direito a cada um, o que nenhuma busca única conseguiria honrar. Acentos são ignorados no MySQL e no MariaDB, e em sites PostgreSQL cujo banco permita instalar a extensão unaccent; nos demais, uma letra acentuada precisa ser digitada como foi informada.';
$string['queueselectedonpage'] = '{$a} selecionadas nesta página';
$string['queuesheetandmore'] = 'e mais {$a}';
$string['queuesheetcancel'] = 'Cancelar {$a} solicitações';
$string['queuesheetcancelcommit'] = 'Cancelar {$a}';
$string['queuesheetcanceldanger'] = 'Cancelar remove a inscrição e o comentário do solicitante.';
$string['queuesheetcancelone'] = 'Cancelar {$a} solicitação';
$string['queuesheetconfirm'] = 'Confirmar {$a} solicitações';
$string['queuesheetconfirmcommit'] = 'Confirmar {$a}';
$string['queuesheetconfirmone'] = 'Confirmar {$a} solicitação';
$string['queuesheetmessage'] = 'Mensagem a estes solicitantes';
$string['queuesheetspans'] = 'Esta seleção abrange {$a} métodos de inscrição. Cada aprovação usa o papel e os grupos do seu próprio método.';
$string['queuesheetwait'] = 'Adiar {$a} solicitações';
$string['queuesheetwaitcommit'] = 'Adiar {$a}';
$string['queuesheetwaitline'] = 'Solicitações adiadas continuam nesta fila até que alguém as decida.';
$string['queuesheetwaitone'] = 'Adiar {$a} solicitação';
$string['queueshowing'] = 'Mostrando {$a->from}-{$a->to} de {$a->total}';
$string['queuestatus'] = 'Situação';
$string['queuestatusall'] = 'Todas';
$string['queuesubmitted'] = 'Enviado com a solicitação';
$string['queueviewcaption'] = 'Solicitações aguardando decisão';
$string['queueviewcloses'] = 'As solicitações encerram em {$a}';
$string['queueviewempty'] = 'Nenhuma solicitação aguarda decisão.';
$string['queueviewgrid'] = 'Visualização em grade';
$string['queueviewloadsummary'] = 'Carregando a capacidade';
$string['queueviewmorefiltersnote'] = 'Os filtros por um campo de texto ou pela data da solicitação ficam na visualização em tabela.';
$string['queueviewmountfailed'] = 'Não foi possível carregar a visualização em grade. Use a visualização em tabela.';
$string['queueviewonly'] = 'Você pode ver estas solicitações, mas não decidi-las.';
$string['queueviewopenreport'] = 'Abrir o relatório';
$string['queueviewpreseteverything'] = 'Tudo o que foi perguntado';
$string['queueviewpresetglance'] = 'A decisão num relance';
$string['report:course_applications'] = 'Solicitações de inscrição';
$string['reportapplicant'] = 'Solicitante';
$string['reportgroup'] = 'Grupo (solicitado ou em que entrou)';
$string['reportgroupchose'] = 'Escolheu: {$a}';
$string['reportgroupcolumn'] = 'Grupo';
$string['reportgroupjoined'] = 'Entrou em {$a}';
$string['reportgroupkey'] = 'Chave: {$a}';
$string['reportgroupwithheld'] = 'Grupos que você não pode ver';
$string['reportnogroup'] = 'Sem grupo';
$string['reportretention'] = 'Os registros de solicitação são mantidos por {$a} após o envio. Depois que uma solicitação é decidida, uma tarefa diária exclui o seu registro quando esse prazo termina, e ele deixa de aparecer neste relatório. Solicitações que ainda aguardam decisão são mantidas qualquer que seja a idade.';
$string['reportretention_change'] = 'Alterar por quanto tempo os registros são mantidos';
$string['reportviewall'] = 'Todas';
$string['reportviewallgroupings'] = 'Todos os agrupamentos';
$string['reportviewallgroups'] = 'Todos os {$a} grupos, os mais lentos primeiro';
$string['reportviewalways'] = 'Sempre exibida';
$string['reportviewany'] = 'Qualquer';
$string['reportviewawaiting'] = 'Aguardando';
$string['reportviewbreakdown'] = 'Detalhar por';
$string['reportviewbucket'] = 'De {$a->from} a {$a->to} d';
$string['reportviewbucketfirst'] = 'Menos de {$a} d';
$string['reportviewbucketlast'] = 'Mais de {$a} d';
$string['reportviewbygroup'] = 'Grupo';
$string['reportviewbymethod'] = 'Método';
$string['reportviewcards'] = 'Cartões';
$string['reportviewclearall'] = 'Limpar tudo';
$string['reportviewclearfilters'] = 'Limpar filtros';
$string['reportviewcleartext'] = 'Limpar texto';
$string['reportviewcolumns'] = 'Colunas';
$string['reportviewcoretable'] = 'Tabela padrão do Moodle';
$string['reportviewcount'] = '{$a} solicitações';
$string['reportviewcount_one'] = '1 solicitação';
$string['reportviewdecided'] = 'Decididas';
$string['reportviewdecisions'] = '{$a} decisões';
$string['reportviewdefaultcolumns'] = 'Colunas padrão';
$string['reportviewdistfirst'] = 'Distribuição da primeira resposta';
$string['reportviewdistresolution'] = 'Distribuição da resolução';
$string['reportviewdownload'] = 'Baixar';
$string['reportviewempty'] = 'Nenhuma solicitação foi feita ainda.';
$string['reportviewfilterchip'] = '{$a->title}: {$a->value}';
$string['reportviewfilters'] = 'Filtros';
$string['reportviewfilterson'] = 'Filtros em vigor: {$a}';
$string['reportviewgrid'] = 'Solicitações, role para ver mais colunas';
$string['reportviewgrouping'] = 'Agrupamento';
$string['reportviewhide'] = 'Ocultar';
$string['reportviewlate'] = '{$a} acima de 7 d';
$string['reportviewless'] = 'Menos';
$string['reportviewloadband'] = 'Carregando o tempo de resposta';
$string['reportviewloaderror'] = 'Não foi possível carregar as solicitações.';
$string['reportviewloading'] = 'Carregando solicitações';
$string['reportviewmeasure'] = 'Medir a espera até';
$string['reportviewmeasurefirst'] = 'Primeira resposta';
$string['reportviewmeasureresolution'] = 'Resolução';
$string['reportviewmedianby'] = 'Mediana por';
$string['reportviewmedianfirst'] = 'Mediana da primeira resposta';
$string['reportviewmedianresolution'] = 'Mediana da resolução';
$string['reportviewmediansub'] = 'Sobre {$a} decididas por uma pessoa';
$string['reportviewmore'] = 'Mais';
$string['reportviewmorefilters'] = 'Mais filtros';
$string['reportviewmorefilters_help'] = 'Os demais filtros do relatório (datas, textos e cada resposta) são definidos na tabela padrão do Moodle.';
$string['reportviewmoregroups'] = 'Mais {$a}: refine a busca.';
$string['reportviewmoregroupsgrouping'] = 'Mais {$a}: refine a busca ou escolha um agrupamento.';
$string['reportviewmountfailed'] = 'Não foi possível carregar a visualização das solicitações. Use a visualização em tabela.';
$string['reportviewnarrowed'] = 'Você vê as solicitações dos seus grupos.';
$string['reportviewnext'] = 'Próxima';
$string['reportviewnodecidermatch'] = 'Nenhuma pessoa que decidiu corresponde.';
$string['reportviewnogroupmatch'] = 'Nenhum grupo corresponde.';
$string['reportviewnomatch'] = 'Nenhuma solicitação corresponde';
$string['reportviewnomatch_help'] = 'Remova um filtro ou a busca para ver mais.';
$string['reportviewnothingopen'] = 'Nada aguardando';
$string['reportviewoldest'] = 'Mais antiga: {$a}';
$string['reportviewopen'] = 'Ainda em aberto';
$string['reportviewopenqueue'] = 'Abrir a fila';
$string['reportviewp90'] = 'Percentil 90';
$string['reportviewp90sub'] = '9 em cada 10 respondidas neste prazo';
$string['reportviewpageof'] = 'Página {$a->page} de {$a->pages}';
$string['reportviewpages'] = 'Páginas';
$string['reportviewperpage'] = 'Por página';
$string['reportviewpresetanswers'] = 'Respostas';
$string['reportviewpresetdecisions'] = 'Decisões';
$string['reportviewpresetgroups'] = 'Grupos';
$string['reportviewpresets'] = 'Conjuntos';
$string['reportviewprevious'] = 'Anterior';
$string['reportviewrange'] = '{$a->from} a {$a->to} de {$a->total}';
$string['reportviewreactview'] = 'Visualização das solicitações';
$string['reportviewremovefilter'] = 'Remover filtro {$a}';
$string['reportviewresetwidths'] = 'Restaurar a largura das colunas';
$string['reportviewresize'] = 'Redimensionar {$a}';
$string['reportviewretry'] = 'Tentar novamente';
$string['reportviewscope'] = 'Mostrar';
$string['reportviewscrollhint'] = 'Role para o lado para ver mais colunas; o solicitante continua visível. Arraste a borda de um cabeçalho para redimensionar a coluna, ou use as setas do teclado sobre ela.';
$string['reportviewsearch'] = 'Buscar por nome';
$string['reportviewsearchchip'] = 'Nome: {$a}';
$string['reportviewsearchdeciders'] = 'Buscar entre {$a} pessoas que decidiram';
$string['reportviewsearchgroup'] = 'Buscar um grupo';
$string['reportviewsearchgroups'] = 'Buscar em {$a} grupos';
$string['reportviewshow'] = 'Mostrar resultados';
$string['reportviewshowcount'] = 'Mostrar {$a}';
$string['reportviewtable'] = 'Tabela';
$string['reportviewtableview'] = 'Visualização em tabela';
$string['reportviewtopgroups'] = 'Os {$a} grupos com mais decisões';
$string['reportviewturnaround'] = 'Tempo de resposta';
$string['reportviewview'] = 'Visualização';
$string['reportviewviewauto'] = 'Automática';
$string['reportviewviewautohint'] = 'Cartões em tela estreita';
$string['reportviewwaitedmore'] = 'Esperou mais de';
$string['reportviewwithoutperson'] = 'Não decididas por uma pessoa';
$string['reportviewwithoutpersonsub'] = 'Admissões por chave e registros sem responsável, fora do tempo de resposta';
$string['requestedfields'] = 'Campos de perfil solicitados';
$string['requestedfields_help'] = 'Os campos de perfil que o solicitante preenche no formulário de solicitação.

* Somente os campos permitidos pela administração do site são oferecidos aqui.
* Um campo marcado como obrigatório precisa ser preenchido para a solicitação ser enviada.
* O que o solicitante digita acompanha a solicitação até quem decide. Só altera o perfil se o solicitante aceitar a oferta de "Após a aprovação".

**Exemplo**: peça a instituição e o departamento para conferir se o solicitante pertence ao público do curso.';
$string['requestedfieldsintro'] = 'Marque um campo para pedi-lo, e também Obrigatório para recusar uma solicitação sem ele.';
$string['requiredtoapply'] = 'Este dado é obrigatório para solicitar';
$string['resolutiontime'] = 'Espera pela resolução';
$string['retention_desc'] = 'Por quanto tempo o registro de uma solicitação de inscrição é mantido após o envio. O registro guarda o comentário e os dados de perfil informados pelo solicitante, junto com a decisão tomada e quem a tomou, e sobrevive à própria inscrição.';
$string['retention_heading'] = 'Registros de solicitações';
$string['retentiondays'] = 'Manter os registros de solicitação por';
$string['retentiondays_desc'] = 'Registros de solicitação mais antigos que este prazo, contado a partir do envio, são excluídos por uma tarefa agendada diária assim que a solicitação sai da fila: uma solicitação que ainda aguarda decisão mantém o seu registro qualquer que seja a idade, e tomar uma decisão não reinicia a contagem. Defina como zero para mantê-los para sempre. Excluir um curso remove imediatamente os dados pessoais de seus registros, qualquer que seja este valor; uma solicitação de eliminação os exclui por completo. Observe que um registro só viaja em um backup do curso quando o backup inclui usuários, portanto, com o padrão global "Incluir usuários inscritos" desligado, um curso que passa pela lixeira volta sem os seus registros de solicitação.';
$string['reviewaddgroup'] = 'Adicionar outro grupo';
$string['reviewaddgroupcount'] = '{$a} grupos para escolher';
$string['reviewaddgrouphelp'] = 'Digite parte do nome ou do código do grupo. Grupos sem vagas aparecem e podem ser escolhidos: o plugin avisa, mas a decisão é sua.';
$string['reviewaddgroupnone'] = 'Nenhum outro grupo escolhido';
$string['reviewaddgroupsearch'] = 'Buscar grupo';
$string['reviewadjust'] = 'Ajustar grupos e papel';
$string['reviewafterplaces'] = 'vagas neste método, {$a->taken} de {$a->total}';
$string['reviewafterseats'] = '{$a->name} com {$a->members} de {$a->seats} vagas ocupadas';
$string['reviewapplicant'] = 'Solicitante';
$string['reviewapplicants'] = 'Solicitações em andamento';
$string['reviewappliedon'] = 'Solicitada em {$a}';
$string['reviewcancel'] = 'Cancelar esta solicitação';
$string['reviewcancelaction'] = 'Cancelar e desinscrever';
$string['reviewcancelconfirm'] = 'Cancelar esta solicitação?';
$string['reviewcancelconfirm_desc'] = 'Cancelar remove a inscrição de {$a} no curso, junto com o comentário enviado. Isso não pode ser desfeito: para reconsiderar depois, adie a solicitação.';
$string['reviewcancelshort'] = 'Cancelar';
$string['reviewconfirm'] = 'Confirmar esta solicitação';
$string['reviewconfirmshort'] = 'Confirmar';
$string['reviewdecision'] = 'A decisão até agora';
$string['reviewdeferredby'] = 'Adiada por {$a->who} em {$a->when}.';
$string['reviewdeferredon'] = 'Adiada em {$a}.';
$string['reviewdetailscount'] = '{$a->filled} de {$a->total} preenchidos';
$string['reviewdetailsempty'] = 'Não preenchido';
$string['reviewdetailsrequired'] = '{$a} obrigatórios';
$string['reviewdetailsrequiredmark'] = '(obrigatório)';
$string['reviewdetailsrequiredone'] = '1 obrigatório';
$string['reviewemptychoice'] = 'Sem nenhum grupo marcado, a aprovação usa os grupos padrão: {$a}.';
$string['reviewemptychoicenone'] = 'Sem nenhum grupo marcado, a aprovação não inclui o solicitante em nenhum grupo.';
$string['reviewenrolments'] = 'Já neste curso';
$string['reviewenrolmentsadmission'] = 'Admissão';
$string['reviewenrolmentsapprovedby'] = 'Aprovada por {$a->who} em {$a->when}';
$string['reviewenrolmentsapprovedon'] = 'Aprovada em {$a}';
$string['reviewenrolmentsbykey'] = 'Pela chave de inscrição de {$a}';
$string['reviewenrolmentsbykeyunnamed'] = 'Por uma chave de inscrição de grupo';
$string['reviewenrolmentsotherroles'] = 'Também tem neste curso';
$string['reviewenrolmentsrole'] = 'Papel';
$string['reviewfullprofile'] = 'Ver perfil completo';
$string['reviewgroupcardfull'] = 'Sem vagas, {$a->members} de {$a->seats}';
$string['reviewgroupcardnolimit'] = 'Sem limite de vagas';
$string['reviewgroupcardseats'] = '{$a->members} de {$a->seats} vagas ocupadas, restam {$a->left}';
$string['reviewgroupchosen'] = 'Escolha do solicitante';
$string['reviewgroupidnumber'] = '{$a->name} ({$a->idnumber})';
$string['reviewgrouping'] = 'Agrupamento';
$string['reviewgroupingall'] = 'Todos os agrupamentos';
$string['reviewgroupkey'] = 'Grupo da chave';
$string['reviewgroupmethod'] = 'Do método';
$string['reviewgroupoption'] = '{$a->name} · {$a->members} de {$a->seats} vagas';
$string['reviewgroupoptionfull'] = '{$a->name} · sem vagas, {$a->members} de {$a->seats}';
$string['reviewhistory'] = 'Solicitações anteriores a este curso';
$string['reviewhistorydecided'] = 'decidida em {$a}';
$string['reviewhistorymany'] = '{$a->count} solicitações anteriores a este curso. A última: {$a->outcome}, {$a->applied}.';
$string['reviewhistoryone'] = '1 solicitação anterior a este curso: {$a->outcome}, {$a->applied}.';
$string['reviewhistoryopen'] = 'Ver o histórico';
$string['reviewhistoryreport'] = 'Abrir o relatório de solicitações';
$string['reviewkeep'] = 'Manter a solicitação';
$string['reviewkeptgroups'] = 'O método também dá grupos que você não pode escolher, e a aprovação os mantém: {$a}.';
$string['reviewmeterafter'] = 'Ao confirmar: {$a->after} de {$a->total}, {$a->left} restantes';
$string['reviewmeterafterfull'] = 'Ao confirmar: {$a->after} de {$a->total}, nenhuma restante';
$string['reviewmeterafterone'] = 'Ao confirmar: {$a->after} de {$a->total}, 1 restante';
$string['reviewmeterafterover'] = 'Ao confirmar: {$a->after} de {$a->total}, acima do limite';
$string['reviewmeterapplicants'] = 'Limite de solicitações';
$string['reviewmetergroupchosen'] = '{$a} (escolha do solicitante)';
$string['reviewmetergroupkey'] = '{$a} (grupo da chave)';
$string['reviewmeterplaces'] = 'Vagas do método';
$string['reviewnavigation'] = 'Solicitações aguardando decisão';
$string['reviewnext'] = 'Próxima: {$a}';
$string['reviewnothingsent'] = 'Nenhum comentário ou dado enviado para mostrar.';
$string['reviewofmany'] = '{$a->taken} de {$a->total}';
$string['reviewoutcome'] = 'Ao confirmar';
$string['reviewoutcomeafter'] = 'Depois: {$a}.';
$string['reviewoutcomemanywithnotice'] = '{$a->who} entra no curso {$a->role} e nos grupos {$a->groups}, e {$a->notice}.';
$string['reviewoutcomenogroupinfowithnotice'] = '{$a->who} entra no curso {$a->role} e {$a->notice}.';
$string['reviewoutcomenonewithnotice'] = '{$a->who} entra no curso {$a->role}, sem grupo, e {$a->notice}.';
$string['reviewoutcomenorole'] = 'sem papel';
$string['reviewoutcomenotchosen'] = 'Não entra em {$a}, o grupo que o solicitante escolheu.';
$string['reviewoutcomenotice'] = 'recebe o aviso da aprovação';
$string['reviewoutcomenotkey'] = 'Não entra em {$a}, o grupo da chave.';
$string['reviewoutcomeonewithnotice'] = '{$a->who} entra no curso {$a->role} e no grupo {$a->groups}, e {$a->notice}.';
$string['reviewoutcomerole'] = 'como {$a}';
$string['reviewownapplication'] = 'Esta é a sua própria solicitação. Você pode vê-la, mas outra pessoa que decide solicitações deve decidi-la.';
$string['reviewpinnedhelp'] = 'Marcados: os grupos que esta aprovação usa, definidos pela chave ou pela escolha do solicitante e pelo método. Adicione outro grupo abaixo.';
$string['reviewprevious'] = 'Anterior: {$a}';
$string['reviewqueue'] = 'Todas as solicitações';
$string['reviewreadonly'] = 'Somente leitura';
$string['reviewroledefaultnamed'] = 'Usar o papel definido no método de inscrição ({$a})';
$string['reviewrules'] = 'Regras a observar';
$string['reviewrulesdeferred'] = '{$a->count} solicitações adiadas ocupam lugar no limite de {$a->limit} solicitações';
$string['reviewrulesdeferredone'] = '1 solicitação adiada ocupa lugar no limite de {$a->limit} solicitações';
$string['reviewrulesdeferredyours'] = '{$a->count} solicitações adiadas nos seus grupos ocupam lugar no limite de {$a->limit} solicitações';
$string['reviewrulesdeferredyoursone'] = '1 solicitação adiada nos seus grupos ocupa lugar no limite de {$a->limit} solicitações';
$string['reviewsignalseats'] = '{$a->name}: {$a->members} de {$a->seats} vagas ocupadas';
$string['reviewtitle'] = '{$a->applicant} - {$a->course}';
$string['reviewviewonly'] = 'Você pode ver esta solicitação, mas não decidi-la.';
$string['reviewwait'] = 'Adiar esta solicitação';
$string['reviewwaiting'] = 'Aguardando decisão há';
$string['reviewwaitingdeferred'] = 'Na lista de espera há';
$string['reviewwaitshort'] = 'Adiar';
$string['reviewwrite'] = 'Escrever ao solicitante ou anotar para o registro';
$string['reviewwritehelp'] = 'Opcional. A mensagem vai para o solicitante com a decisão; a anotação fica só no registro.';
$string['roleonapproval'] = 'Papel na aprovação';
$string['roleonapproval_help'] = 'O papel que o solicitante aprovado recebe no curso.

* Solicitar não dá papel nenhum: ele é atribuído quando a solicitação é aprovada.
* Quem decide e pode escolher a decisão pode dar outro papel na página de revisão, entre os papéis que pode atribuir.
* O papel pertence a este método, por isso é removido junto com a inscrição.

**Um método por papel**: para receber estudantes e moderadores no mesmo curso, crie dois métodos, um para cada papel. Um papel que pode decidir solicitações torna este um método de recrutamento: veja "Quem decide as solicitações deste método?".';
$string['saveandeditfields'] = 'Salvar e editar campos da solicitação';
$string['saveforfuture'] = 'Salvar estes dados para a próxima vez?';
$string['saveforfuture_desc'] = 'Nada foi salvo no seu perfil ainda. Ao salvar, você não precisará informar estes dados novamente na próxima solicitação.';
$string['saveforfutureinstance'] = 'Oferecer salvar os dados no perfil';
$string['saveforfutureinstance_help'] = 'Depois de enviar uma solicitação, o solicitante recebe a oferta de salvar no próprio perfil os dados que digitou.

* Somente os campos que o solicitante pode editar são gravados, e apenas quando ele aceita a oferta.
* Uma resposta vazia nunca apaga o que o perfil já tem.
* Disponível apenas enquanto a administração do site permitir.

**Exemplo**: o formulário pede o departamento. O solicitante que aceita a oferta não precisa digitá-lo de novo no próximo curso.';
$string['sectionafter'] = 'Após a aprovação';
$string['sectionafter_help'] = 'O que vem depois de uma aprovação: quanto tempo dura a inscrição, se alguém é avisado antes de ela terminar, e se o solicitante recebe a oferta de salvar no perfil os dados que digitou.';
$string['sectiondeciders'] = 'Quem decide e quem é avisado';
$string['sectiondeciders_help'] = 'Quem decide as solicitações deste método, e quem recebe uma mensagem quando chega uma.

As duas coisas seguem a mesma regra: num curso em modo de grupos separados, o moderador de um grupo decide, e é avisado, apenas das solicitações desse grupo.';
$string['sectionform'] = 'Formulário de solicitação';
$string['sectionform_help'] = 'O que o solicitante lê e preenche: o texto acima do formulário, uma caixa de comentário, campos de perfil e as perguntas próprias do método.

Cada método tem o seu formulário, então dois métodos do mesmo curso podem pedir coisas diferentes.';
$string['sectiongeneral'] = 'Geral';
$string['sectiongeneral_help'] = 'O básico deste método de inscrição: o nome exibido na página de inscrição do curso, se ele está em uso e recebe novas solicitações, e o papel que o solicitante aprovado recebe.';
$string['sectiongroups'] = 'Grupos';
$string['sectiongroups_help'] = 'Como o grupo do solicitante é decidido e os grupos em que o solicitante aprovado entra.

Solicitar não coloca ninguém em grupo: a participação segue a aprovação. A modalidade de grupo do curso, informada primeiro, diz se os grupos também separam quem decide cada solicitação.';
$string['sectionnotify'] = 'Notificações';
$string['sectionnotify_help'] = 'Quem é avisado quando chega uma nova solicitação.';
$string['sectionwhen'] = 'Quando e quantos';
$string['sectionwhen_help'] = 'Quando as solicitações são recebidas, e quantas. Estes limites são só deste método: outro método do curso conta os próprios solicitantes e vagas.';
$string['sectionwho'] = 'Quem pode solicitar';
$string['sectionwho_help'] = 'Restringe quem pode solicitar por este método. Sem restrição, qualquer pessoa que chegue à página de inscrição do curso pode solicitar.';
$string['selectapplicant'] = 'Selecionar {$a}';
$string['sendexpirynotificationstask'] = 'Envio de notificações de expiração de inscrição';
$string['startapplication'] = 'Iniciar solicitação';
$string['status'] = 'Permitir as inscrições existentes';
$string['status_desc'] = 'Permitir o acesso ao curso a usuários inscritos internamente.';
$string['status_help'] = 'Liga ou desliga o método inteiro.

* **Sim**: as inscrições aprovadas dão acesso ao curso, e as solicitações podem ser enviadas e decididas.
* **Não**: toda inscrição feita por este método fica suspensa, e ninguém pode enviar solicitação.

**Exemplo**: para parar de receber solicitações sem afetar quem já está inscrito, deixe aqui em Sim e coloque "Aceitar novas solicitações" em Não.';
$string['submissiondecidedby'] = 'Decidido por';
$string['submissionenrolment'] = 'Inscrição agora';
$string['submissionmethod'] = 'Método de inscrição';
$string['submissionmethodgone'] = 'Um método excluído depois';
$string['submissionoutcome'] = 'Resultado';
$string['submissionsnapshot'] = 'Dados informados';
$string['submissionstatus'] = 'Situação';
$string['submissionstatusapproved'] = 'Aprovada';
$string['submissionstatuscancelled'] = 'Cancelada';
$string['submissionstatuspending'] = 'Pendente';
$string['submissionstatuswaiting'] = 'Adiada';
$string['submissiontimecreated'] = 'Enviada em';
$string['submissiontimedecided'] = 'Decidida em';
$string['submissiontimefirstdecided'] = 'Primeira decisão em';
$string['submitapplication'] = 'Enviar solicitação';
$string['submitted_info'] = 'Informações da solicitação';
$string['submittedprofile'] = 'Dados enviados';
$string['summaryaccepting'] = 'Aceitando solicitações';
$string['summaryacceptinguntil'] = 'Aceitando solicitações até {$a}';
$string['summarychoice'] = 'Os solicitantes escolhem entre {$a} grupos';
$string['summaryclosed'] = 'Solicitações encerradas em {$a}';
$string['summarydecidersallgroups'] = 'Decidido somente por quem vê todos os grupos';
$string['summarydisabled'] = 'Método desativado: ninguém pode solicitar';
$string['summaryfull'] = 'Não aceita solicitações: o limite de solicitações foi atingido';
$string['summarygroupmode'] = 'Modalidade de grupo do curso: {$a}';
$string['summarykeysany'] = 'Uma chave de grupo válida inscreve na hora; as demais solicitações passam por análise';
$string['summarykeysoff'] = 'Chaves de inscrição de grupo não são usadas';
$string['summarykeysrequired'] = 'Só com chave: uma chave de grupo válida inscreve na hora';
$string['summarykeysreview'] = 'Só com chave: uma chave de grupo válida envia a solicitação para análise';
$string['summarylabel'] = 'Resumo deste método';
$string['summarynotaccepting'] = 'Não aceita novas solicitações';
$string['summarynotopen'] = 'Solicitações abrem em {$a}';
$string['summaryplaces'] = '{$a->taken} de {$a->limit} vagas ocupadas';
$string['summaryplacesnolimit'] = 'Vagas ocupadas: {$a}, sem limite';
$string['syncenrolmentstask'] = 'Sincronização de inscrições expiradas';
$string['updateprofile'] = 'Salvar no meu perfil';
$string['user_profile'] = 'Perfil do usuário';
$string['waitbykey'] = 'Pela chave';
$string['waitdays'] = '{$a} d';
$string['waitdayshours'] = '{$a->days} d {$a->hours} h';
$string['waitdeferredopen'] = 'Adiada, em aberto há {$a}';
$string['waithours'] = '{$a} h';
$string['waitlastdecision'] = 'Até a última decisão';
$string['waitmail_desc'] = '';
$string['waitmail_heading'] = 'E-mail de adiamento';
$string['waitmailcontent'] = 'Conteúdo do e-mail de adiamento';
$string['waitmailcontent_default'] = '<p>Olá {firstname},</p><p>Sua solicitação de inscrição em {content} foi adiada. Nenhuma decisão foi tomada ainda; você será avisado por e-mail quando ela for decidida.</p>';
$string['waitmailcontent_desc'] = 'Deixe em branco para usar o texto que este plugin já traz. Use as marcações a seguir para substituir o conteúdo do e-mail por dados do Moodle.<br/>{firstname}: o nome do usuário; {content}: o nome do curso; {lastname}: o sobrenome do usuário; {username}: o nome de usuário do cadastro';
$string['waitmailsubject'] = 'Assunto do e-mail de adiamento';
$string['waitmailsubject_default'] = 'Sua solicitação de inscrição em {$a} foi adiada';
$string['waitmailsubject_desc'] = 'Deixe em branco para usar o texto que este plugin já traz.';
$string['waitminutes'] = '{$a} min';
$string['waitopen'] = '{$a}, em aberto';
$string['waitresolvedafter'] = 'Resolvida após {$a}';
$string['waittime'] = 'Espera';
$string['warndecidingbygroups'] = 'Este método atribui um papel que decide solicitações, e quem decide por cada grupo decide as suas solicitações: o moderador de um grupo poderia aprovar outro moderador. Defina "Quem decide as solicitações deste método?" como "Somente quem vê todos os grupos".';
$string['warnofferleak'] = 'Oferecidos aqui e atribuídos na aprovação por outro método deste curso, cujo papel decide solicitações: {$a}. Quem esse método aprova decidiria todas as solicitações para esses grupos.';
$string['warnofferleakunnamed'] = 'Um grupo oferecido aqui é atribuído na aprovação por outro método deste curso, cujo papel decide solicitações. Quem esse método aprova decidiria todas as solicitações para esse grupo.';
$string['whatyouentered'] = 'O que você informou';
$string['withoutkey'] = 'Enviar minha inscrição para análise sem a chave';
$string['youwillchecknddetails'] = 'Você será convidado a conferir alguns dados antes de enviar sua solicitação para análise.';

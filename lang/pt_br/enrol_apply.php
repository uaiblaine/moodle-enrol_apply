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

$string['allowedfields'] = 'Campos de perfil que os cursos podem solicitar';
$string['allowedfields_desc'] = 'O conjunto de campos de perfil que um curso pode coletar em uma solicitação de matrícula. O professor escolhe nesta lista em cada método de matrícula; desmarcar um campo aqui também o remove de todos os métodos existentes, pois o conjunto escolhido é verificado contra esta lista a cada uso.';
$string['allowprofilewrite'] = 'Permitir que cursos ofereçam salvar dados do perfil';
$string['allowprofilewrite_desc'] = 'Quando ativado, um método de matrícula pode oferecer ao solicitante a opção de salvar no próprio perfil os dados que ele informou. O solicitante é sempre consultado antes, e apenas os campos que ele pode editar são gravados. Quando desativado, os solicitantes veem quais dados estão faltando e são encaminhados à página do perfil.';
$string['applicationcancelednotification'] = 'Sua solicitação de matrícula no curso foi cancelada.';
$string['applicationconfirmednotification'] = 'Sua solicitação de matrícula no curso foi confirmada.';
$string['applicationdeferrednotification'] = 'Sua solicitação de matrícula no curso foi adiada (você está na lista de espera).';
$string['applicationgone'] = 'Esta solicitação de matrícula não está mais aguardando decisão. Ela pode já ter sido decidida, ou a matrícula pode ter sido removida.';
$string['applicationsubmitted'] = 'Solicitação enviada';
$string['applicationsubmitted_body'] = 'Sua solicitação foi enviada e aguarda decisão. Você será avisado assim que ela for analisada.';
$string['applicationsupdated'] = 'As solicitações de matrícula selecionadas foram atualizadas.';
$string['apply:config'] = 'Configurar instâncias de matrícula por aprovação';
$string['apply:manage'] = 'Gerenciar matrículas de usuários';
$string['apply:manageapplications'] = 'Gerenciar solicitações de matrícula';
$string['apply:unenrol'] = 'Cancelar a matrícula de usuários no curso';
$string['apply:unenrolself'] = 'Cancelar a própria matrícula no curso';
$string['apply:viewreports'] = 'Ver o relatório de solicitações de inscrição';
$string['applycomment'] = 'Comentário';
$string['applydate'] = 'Data da solicitação';
$string['applymanage'] = 'Gerenciar solicitações de matrícula';
$string['applyuser'] = 'Nome / Sobrenome';
$string['backtoapplications'] = 'Voltar às solicitações de matrícula';
$string['btncancel'] = 'Cancelar solicitações';
$string['btnconfirm'] = 'Confirmar solicitações';
$string['btnwait'] = 'Adiar solicitações';
$string['bulkapplicants'] = 'Solicitantes selecionados';
$string['bulkcancel'] = 'Cancelar solicitações de matrícula';
$string['bulkcanceldesc'] = 'As solicitações listadas abaixo serão canceladas e os solicitantes desmatriculados. Quem estiver na seleção sem aguardar uma decisão não será alterado.';
$string['bulkconfirm'] = 'Confirmar solicitações de matrícula';
$string['bulkconfirmdesc'] = 'As solicitações listadas abaixo serão confirmadas e os solicitantes matriculados. Quem estiver na seleção sem aguardar uma decisão não será alterado.';
$string['bulkdecided'] = 'Solicitações de matrícula decididas: {$a}';
$string['bulknothingdecided'] = 'Nenhum dos usuários selecionados tinha solicitação a decidir.';
$string['bulknotpermitted'] = 'Você não pode decidir solicitações de matrícula neste curso.';
$string['bulkskipped'] = 'Usuários selecionados sem solicitação aguardando decisão, mantidos como estavam: {$a}';
$string['bulkunchanged'] = 'Solicitações mantidas como estavam: {$a}';
$string['bulkwait'] = 'Adiar solicitações de matrícula';
$string['bulkwaitdesc'] = 'As solicitações listadas abaixo serão movidas para a lista de espera. Quem estiver na seleção sem aguardar uma decisão não será alterado.';
$string['cancelmail_desc'] = '';
$string['cancelmail_heading'] = 'E-mail de cancelamento';
$string['cancelmailcontent'] = 'Conteúdo do e-mail de cancelamento';
$string['cancelmailcontent_desc'] = 'Use as marcações a seguir para substituir o conteúdo do e-mail por dados do Moodle.<br/>{firstname}: o nome do usuário; {content}: o nome do curso; {lastname}: o sobrenome do usuário; {username}: o nome de usuário do cadastro';
$string['cancelmailsubject'] = 'Assunto do e-mail de cancelamento';
$string['cancelmailsubject_desc'] = '';
$string['canntenrolearly'] = 'Você ainda não pode solicitar matrícula; as solicitações abrem em {$a}.';
$string['canntenrollate'] = 'Você não pode mais solicitar matrícula, pois as solicitações se encerraram em {$a}.';
$string['cantenrol'] = 'A matrícula está desativada ou inativa';
$string['checkyourdetails'] = 'Confira seus dados';
$string['cohortnonmemberinfo'] = 'A inscrição neste curso é restrita aos membros de um coorte específico. Se você acredita que deveria ter acesso, entre em contato com a administração do curso.';
$string['cohortonly'] = 'Somente membros do coorte';
$string['cohortonly_help'] = 'As solicitações podem ser restritas somente aos membros de um coorte específico. Alterar esta configuração não afeta as solicitações nem as matrículas existentes.';
$string['cohortunresolved'] = 'Este método de matrícula está restrito a um coorte que não existe neste site, portanto nenhuma solicitação pode ser aceita. Entre em contato com a administração do curso.';
$string['comment'] = 'Comentário';
$string['confirmalldetails'] = 'Estes dados estão atualizados';
$string['confirmenrol'] = 'Gerenciar solicitação';
$string['confirmfield'] = '\'{$a}\' está atualizado';
$string['confirmmail_desc'] = '';
$string['confirmmail_heading'] = 'E-mail de confirmação';
$string['confirmmailcontent'] = 'Conteúdo do e-mail de confirmação';
$string['confirmmailcontent_desc'] = 'Use as marcações a seguir para substituir o conteúdo do e-mail por dados do Moodle.<br/>{firstname}: o nome do usuário; {content}: o nome do curso; {lastname}: o sobrenome do usuário; {username}: o nome de usuário do cadastro; {timeend}: a data de expiração da matrícula';
$string['confirmmailsubject'] = 'Assunto do e-mail de confirmação';
$string['confirmmailsubject_desc'] = '';
$string['confirmusers'] = 'Confirmar matrículas';
$string['confirmusers_desc'] = 'As solicitações marcadas com uma barra cinza estão na lista de espera.';
$string['coursename'] = 'Curso';
$string['custom_label'] = 'Rótulo personalizado';
$string['custom_label_help'] = 'Encabeça a caixa de comentário do solicitante e a coluna de comentário na fila de aprovação e na página de revisão, de modo que a pergunta feita e as respostas lidas usem a mesma redação. Não tem efeito a menos que \'Campo de comentário\' esteja como Sim. Deixe em branco para usar a redação padrão.';
$string['datasource:applications'] = 'Solicitações de inscrição';
$string['decideapplication'] = 'Decidir esta solicitação';
$string['decisiongroups'] = 'Grupos para ingressar na aprovação';
$string['decisiongroups_help'] = 'Os solicitantes aprovados entram nestes grupos. Deixe em branco para usar os grupos configurados no método de inscrição.';
$string['decisionrole'] = 'Papel a atribuir na aprovação';
$string['decisionrole_help'] = 'Os solicitantes aprovados recebem este papel no curso. Deixe como está para usar o papel configurado no método de inscrição. Só são oferecidos os papéis que você pode atribuir neste curso, e apenas a aprovação usa esta escolha - adiar ou cancelar a ignora.';
$string['decisionroledefault'] = 'Usar o papel definido no método de inscrição';
$string['defaultperiod'] = 'Duração padrão da matrícula';
$string['defaultperiod_desc'] = 'Tempo padrão de validade da matrícula. Se for zero, a duração da matrícula será ilimitada por padrão.';
$string['defaultperiod_help'] = 'Tempo padrão de validade da matrícula, contado a partir do momento em que o usuário é matriculado. Se desativado, a duração da matrícula será ilimitada por padrão.';
$string['defaultrole_desc'] = 'Papel atribuído ao usuário quando a solicitação de matrícula é aprovada.';
$string['detailsthattravel'] = 'Dados que acompanham sua solicitação';
$string['detailsthattravel_desc'] = 'Estes dados são enviados a quem analisar sua solicitação. Editá-los aqui não altera o seu perfil.';
$string['editdescription'] = 'Descrição da área de texto';
$string['enrolenddate'] = 'Encerramento das solicitações';
$string['enrolenddate_help'] = 'Se ativado, as solicitações só poderão ser enviadas até esta data. Isso é diferente da duração da matrícula, que define por quanto tempo uma matrícula aprovada permanece válida.';
$string['enrolenddaterror'] = 'A data de encerramento das solicitações não pode ser anterior à data de abertura';
$string['enrolmentactive'] = 'Matriculado';
$string['enrolmentgone'] = 'Não está mais matriculado';
$string['enrolmentsuspended'] = 'Suspenso';
$string['enrolmentunknown'] = 'Desconhecido';
$string['enrolmentwaiting'] = 'Na lista de espera';
$string['enrolstartdate'] = 'Abertura das solicitações';
$string['enrolstartdate_help'] = 'Se ativado, as solicitações só poderão ser enviadas a partir desta data. Isso é diferente da duração da matrícula, que define por quanto tempo uma matrícula aprovada permanece válida.';
$string['entity:submission'] = 'Solicitação de inscrição';
$string['expiredaction'] = 'Ação na expiração da matrícula';
$string['expiredaction_help'] = 'Selecione a ação a ser executada quando a matrícula do usuário expirar. Observe que alguns dados e configurações do usuário são removidos do curso durante o cancelamento da matrícula.';
$string['expiry_desc'] = '';
$string['expiry_heading'] = 'Configurações de expiração';
$string['expirymessageenrolledbody'] = 'Prezado(a) {$a->user},

Esta é uma notificação de que sua matrícula no curso \'{$a->course}\' expira em {$a->timeend}.

Se precisar de ajuda, entre em contato com {$a->enroller}.';
$string['expirymessageenrolledsubject'] = 'Notificação de expiração de matrícula';
$string['expirymessageenrollerbody'] = 'As matrículas no curso \'{$a->course}\' expirarão nos próximos {$a->threshold} para os seguintes usuários:

    {$a->users}

Para estender as matrículas, acesse {$a->extendurl}';
$string['expirymessageenrollersubject'] = 'Notificação de expiração de matrícula';
$string['expirynotifyall'] = 'Professor e usuário matriculado';
$string['expirynotifyenroller'] = 'Somente o professor';
$string['expirynotifyhour_desc'] = 'Hora do dia em que as notificações de expiração de matrícula são enviadas.';
$string['fieldrequired'] = 'Obrigatório';
$string['gotoprofile'] = 'Ir para o meu perfil';
$string['group'] = 'Atribuição de grupos';
$string['group_help'] = 'Você pode atribuir nenhum ou vários grupos. Os membros são adicionados quando a solicitação de matrícula é aprovada.';
$string['invalidformaction'] = 'Ação desconhecida solicitada para as matrículas selecionadas.';
$string['lockedby'] = 'Dados definidos pela sua instituição';
$string['mailtoteacher_subject'] = 'Nova solicitação de matrícula!';
$string['maxapplicants'] = 'Número máximo de solicitações';
$string['maxapplicants_help'] = 'O maior número de solicitações que este método de inscrição aceita. Solicitações pendentes, adiadas e aprovadas contam para este limite; uma inscrição cujo período terminou não conta, então este número não coincide com a coluna Usuários da página de métodos de inscrição. 0 significa sem limite. Os solicitantes nunca veem este número - ao ser atingido, apenas são informados de que as solicitações estão encerradas.';
$string['maxenrolled'] = 'Número máximo de usuários matriculados';
$string['maxenrolled_help'] = 'Define o número máximo de usuários que podem solicitar matrícula neste curso. 0 significa sem limite.';
$string['maxenrolledreached'] = 'Não estamos mais aceitando solicitações de matrícula.';
$string['messageprovider:application'] = 'Notificações de solicitação de matrícula em curso';
$string['messageprovider:cancelation'] = 'Notificações de cancelamento de solicitação de matrícula';
$string['messageprovider:confirmation'] = 'Notificações de confirmação de solicitação de matrícula';
$string['messageprovider:expiry_notification'] = 'Notificações de expiração de matrícula';
$string['messageprovider:waitinglist'] = 'Notificações de adiamento de solicitação de matrícula';
$string['newapplicationnotification'] = 'Há uma nova solicitação de matrícula aguardando análise.';
$string['newenrols'] = 'Permitir novas solicitações de matrícula no curso';
$string['newenrols_desc'] = 'Permitir, por padrão, que usuários solicitem matrícula em novas instâncias.';
$string['nocomment'] = 'O solicitante não escreveu nada.';
$string['nofieldsoffered'] = 'No momento a administração não permite coletar nenhum campo de perfil junto com a solicitação.';
$string['nothingtoprovide'] = 'Não há nada a preencher. Envie para solicitar inscrição em {$a}.';
$string['notification'] = 'Solicitação de matrícula enviada com sucesso. Você será notificado por e-mail quando a sua matrícula for confirmada.';
$string['notify_desc'] = 'Defina quem é notificado sobre novas solicitações de matrícula.';
$string['notify_heading'] = 'Configurações de notificação';
$string['notifyapprovaltask'] = 'Envio de notificação de aprovação de matrícula';
$string['notifycoursebased'] = 'Notificação de nova solicitação de matrícula (por instância, ex.: professores do curso)';
$string['notifycoursebased_desc'] = 'Padrão para novas instâncias: notificar todos que possuem a capacidade \'Gerenciar solicitações de matrícula\' no curso correspondente (ex.: professores e gerentes)';
$string['notifyglobal'] = 'Notificação de nova solicitação de matrícula (global, ex.: gerentes e administradores)';
$string['notifyglobal_desc'] = 'Defina quem é notificado sobre novas solicitações de matrícula em qualquer curso.';
$string['opt_commentaryzone'] = 'Campo de comentário';
$string['opt_commentaryzone_help'] = 'Sim -> ativa o campo de comentário no formulário de matrícula';
$string['outcomeapproved'] = 'Aprovado, matriculado';
$string['outcomeawaiting'] = 'Aguardando decisão';
$string['outcomecancelled'] = 'Cancelado';
$string['outcomeexpired'] = 'Aprovado e depois expirado';
$string['outcomemessage'] = 'Mensagem ao solicitante';
$string['outcomemessage_help'] = 'Incluída na mensagem que o solicitante recebe com a sua decisão. Deixe em branco para enviar apenas o texto padrão.';
$string['outcomeneverdecided'] = 'Nunca decidido e não está mais matriculado';
$string['outcomesuspended'] = 'Aprovado e depois suspenso';
$string['outcomeunenrolled'] = 'Aprovado e depois removido da matrícula';
$string['outcomewaiting'] = 'Na lista de espera';
$string['places'] = 'Vagas';
$string['places_help'] = 'Quantos solicitantes este método de inscrição pode ter aprovados ao mesmo tempo. Apenas inscrições aprovadas contam, então o método pode aceitar mais solicitações do que tem vagas - que é justamente a intenção, já que nem todo solicitante é aprovado. Uma inscrição cujo período terminou libera sua vaga. 0 significa sem limite. Atingir o limite não bloqueia uma aprovação: o gestor é avisado e decide.';
$string['placesfull'] = 'Todas as {$a} vagas deste método de inscrição estão ocupadas.';
$string['placestaken'] = 'Vagas ocupadas: {$a->taken} de {$a->limit}.';
$string['pluginname'] = 'Matrículas solicitadas';
$string['pluginname_desc'] = 'Com este plugin os usuários podem solicitar matrícula em um curso. Um professor ou gerente do site precisa aprovar a solicitação antes de o usuário ser matriculado.';
$string['privacy:applicationpath'] = 'Solicitação de matrícula';
$string['privacy:metadata:enrol_apply_applicationinfo'] = 'Informações enviadas junto com uma solicitação de matrícula em curso.';
$string['privacy:metadata:enrol_apply_applicationinfo:comment'] = 'O comentário escrito pelo usuário ao solicitar a matrícula no curso.';
$string['privacy:metadata:enrol_apply_applicationinfo:userenrolmentid'] = 'A matrícula à qual a solicitação pertence, que identifica o usuário solicitante e o curso.';
$string['privacy:metadata:enrol_apply_submission'] = 'O registro durável de uma solicitação de inscrição no curso: o que foi enviado, o que foi decidido, por quem e quando.';
$string['privacy:metadata:enrol_apply_submission:comment'] = 'O comentário que o usuário escreveu ao solicitar a inscrição no curso.';
$string['privacy:metadata:enrol_apply_submission:courseid'] = 'O curso ao qual a solicitação foi enviada.';
$string['privacy:metadata:enrol_apply_submission:decidedby'] = 'O usuário que aprovou, adiou ou cancelou a solicitação.';
$string['privacy:metadata:enrol_apply_submission:decidedgroups'] = 'Os grupos que o responsável pela decisão escolheu para o solicitante entrar na aprovação.';
$string['privacy:metadata:enrol_apply_submission:decidedrole'] = 'O papel que o responsável pela decisão escolheu para o solicitante ter na aprovação.';
$string['privacy:metadata:enrol_apply_submission:enrolid'] = 'O método de inscrição ao qual a solicitação foi enviada.';
$string['privacy:metadata:enrol_apply_submission:outcomemessage'] = 'A mensagem que o responsável pela decisão escreveu ao solicitante.';
$string['privacy:metadata:enrol_apply_submission:status'] = 'Se a solicitação está pendente, aprovada, na lista de espera ou cancelada.';
$string['privacy:metadata:enrol_apply_submission:timecreated'] = 'A data e hora em que a solicitação foi enviada.';
$string['privacy:metadata:enrol_apply_submission:timedecided'] = 'A data e hora em que a solicitação foi decidida.';
$string['privacy:metadata:enrol_apply_submission:userenrolmentid'] = 'A inscrição para a qual a solicitação foi enviada, quando ela ainda existe.';
$string['privacy:metadata:enrol_apply_submission:userid'] = 'O usuário que enviou a solicitação.';
$string['privacy:metadata:enrol_apply_submission:userinfodata'] = 'Os dados de perfil que o usuário informou no formulário de solicitação.';
$string['privacy:methodpath'] = 'Método de inscrição {$a}';
$string['privacy:recordpath'] = 'Registro de solicitação {$a}';
$string['privacy:roleapplicant'] = 'Solicitações que você enviou';
$string['privacy:roledecider'] = 'Solicitações que você decidiu';
$string['privacy:trailpath'] = 'Registros de solicitações de inscrição';
$string['profileincomplete'] = 'Faltam alguns dados no seu perfil';
$string['profileincomplete_desc'] = 'Sua solicitação foi enviada. Estes dados ainda não constam do seu perfil, e este site não permite que cursos os preencham por você.';
$string['profilenotupdated'] = 'Não havia nada a salvar no seu perfil.';
$string['profilenow'] = 'Seu perfil hoje';
$string['profileupdated'] = 'Seu perfil foi atualizado.';
$string['purgesubmissionstask'] = 'Excluir registros de solicitação de inscrição fora do prazo de retenção';
$string['report:course_applications'] = 'Solicitações de inscrição';
$string['requestedfields'] = 'Campos de perfil solicitados';
$string['requestedfields_help'] = 'Os campos de perfil que o solicitante deve preencher. Somente os campos permitidos pela administração do site são oferecidos aqui. Marcar um campo como obrigatório impede o envio da solicitação sem ele.';
$string['requiredtoapply'] = 'Este dado é obrigatório para solicitar';
$string['retention_desc'] = 'Por quanto tempo o registro de uma solicitação de inscrição é mantido após o envio. O registro guarda o comentário e os dados de perfil informados pelo solicitante, junto com a decisão tomada e quem a tomou, e sobrevive à própria inscrição.';
$string['retention_heading'] = 'Registros de solicitações';
$string['retentiondays'] = 'Manter os registros de solicitação por';
$string['retentiondays_desc'] = 'Registros de solicitação mais antigos que este prazo são excluídos por uma tarefa agendada diária, tenham sido decididos ou não. Defina como zero para mantê-los para sempre. Excluir um curso remove imediatamente os dados pessoais de seus registros, qualquer que seja este valor; uma solicitação de eliminação os exclui por completo. Observe que um registro só viaja em um backup do curso quando o backup inclui usuários, portanto, com o padrão global "Incluir usuários inscritos" desligado, um curso que passa pela lixeira volta sem os seus registros de solicitação.';
$string['reviewcancel'] = 'Cancelar esta solicitação';
$string['reviewconfirm'] = 'Confirmar esta solicitação';
$string['reviewnavigation'] = 'Solicitações aguardando decisão';
$string['reviewnext'] = 'Próxima: {$a}';
$string['reviewprevious'] = 'Anterior: {$a}';
$string['reviewwait'] = 'Adiar esta solicitação';
$string['saveforfuture'] = 'Salvar estes dados para a próxima vez?';
$string['saveforfuture_desc'] = 'Nada foi salvo no seu perfil ainda. Ao salvar, você não precisará informar estes dados novamente na próxima solicitação.';
$string['saveforfutureinstance'] = 'Oferecer salvar os dados no perfil';
$string['saveforfutureinstance_help'] = 'Depois de enviar uma solicitação, oferecer ao solicitante a opção de salvar no próprio perfil os dados informados. Somente os campos que o solicitante pode editar são gravados, e apenas quando ele aceita a oferta. Isso só fica disponível enquanto a administração do site permitir.';
$string['selectapplicant'] = 'Selecionar {$a}';
$string['sendexpirynotificationstask'] = 'Envio de notificações de expiração de matrícula';
$string['startapplication'] = 'Iniciar solicitação';
$string['status'] = 'Aceitar matrícula após aprovação';
$string['status_desc'] = 'Permitir o acesso ao curso a usuários matriculados internamente.';
$string['submissiondecidedby'] = 'Decidido por';
$string['submissionenrolment'] = 'Matrícula agora';
$string['submissionmethod'] = 'Método de inscrição';
$string['submissionoutcome'] = 'Resultado';
$string['submissionsnapshot'] = 'Dados informados';
$string['submissionstatus'] = 'Situação';
$string['submissionstatusapproved'] = 'Aprovada';
$string['submissionstatuscancelled'] = 'Cancelada';
$string['submissionstatuspending'] = 'Pendente';
$string['submissionstatuswaiting'] = 'Lista de espera';
$string['submissiontimecreated'] = 'Enviada em';
$string['submissiontimedecided'] = 'Decidida em';
$string['submitapplication'] = 'Enviar solicitação';
$string['submitted_info'] = 'Informações da solicitação';
$string['submittedprofile'] = 'Dados enviados com esta solicitação';
$string['syncenrolmentstask'] = 'Sincronização de matrículas expiradas';
$string['updateprofile'] = 'Salvar no meu perfil';
$string['user_profile'] = 'Perfil do usuário';
$string['waitmail_desc'] = '';
$string['waitmail_heading'] = 'E-mail de lista de espera';
$string['waitmailcontent'] = 'Conteúdo do e-mail de lista de espera';
$string['waitmailcontent_desc'] = 'Use as marcações a seguir para substituir o conteúdo do e-mail por dados do Moodle.<br/>{firstname}: o nome do usuário; {content}: o nome do curso; {lastname}: o sobrenome do usuário; {username}: o nome de usuário do cadastro';
$string['waitmailsubject'] = 'Assunto do e-mail de lista de espera';
$string['waitmailsubject_desc'] = '';
$string['whatyouentered'] = 'O que você informou';
$string['youwillchecknddetails'] = 'Você será convidado a conferir alguns dados antes de enviar sua solicitação para análise.';

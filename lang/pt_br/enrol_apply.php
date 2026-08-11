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

$string['applicationcancelednotification'] = 'Sua solicitação de matrícula no curso foi cancelada.';
$string['applicationconfirmednotification'] = 'Sua solicitação de matrícula no curso foi confirmada.';
$string['applicationdeferrednotification'] = 'Sua solicitação de matrícula no curso foi adiada (você está na lista de espera).';
$string['applicationsupdated'] = 'As solicitações de matrícula selecionadas foram atualizadas.';
$string['apply:config'] = 'Configurar instâncias de matrícula por aprovação';
$string['apply:manage'] = 'Gerenciar matrículas de usuários';
$string['apply:manageapplications'] = 'Gerenciar solicitações de matrícula';
$string['apply:unenrol'] = 'Cancelar a matrícula de usuários no curso';
$string['apply:unenrolself'] = 'Cancelar a própria matrícula no curso';
$string['applycomment'] = 'Comentário';
$string['applydate'] = 'Data da solicitação';
$string['applymanage'] = 'Gerenciar solicitações de matrícula';
$string['applyuser'] = 'Nome / Sobrenome';
$string['btncancel'] = 'Cancelar solicitações';
$string['btnconfirm'] = 'Confirmar solicitações';
$string['btnwait'] = 'Adiar solicitações';
$string['cancelmail_desc'] = '';
$string['cancelmail_heading'] = 'E-mail de cancelamento';
$string['cancelmailcontent'] = 'Conteúdo do e-mail de cancelamento';
$string['cancelmailcontent_desc'] = 'Use as marcações a seguir para substituir o conteúdo do e-mail por dados do Moodle.<br/>{firstname}: o nome do usuário; {content}: o nome do curso; {lastname}: o sobrenome do usuário; {username}: o nome de usuário do cadastro';
$string['cancelmailsubject'] = 'Assunto do e-mail de cancelamento';
$string['cancelmailsubject_desc'] = '';
$string['cantenrol'] = 'A matrícula está desativada ou inativa';
$string['comment'] = 'Comentário';
$string['confirmenrol'] = 'Gerenciar solicitação';
$string['confirmmail_desc'] = '';
$string['confirmmail_heading'] = 'E-mail de confirmação';
$string['confirmmailcontent'] = 'Conteúdo do e-mail de confirmação';
$string['confirmmailcontent_desc'] = 'Use as marcações a seguir para substituir o conteúdo do e-mail por dados do Moodle.<br/>{firstname}: o nome do usuário; {content}: o nome do curso; {lastname}: o sobrenome do usuário; {username}: o nome de usuário do cadastro; {timeend}: a data de expiração da matrícula';
$string['confirmmailsubject'] = 'Assunto do e-mail de confirmação';
$string['confirmmailsubject_desc'] = '';
$string['confirmusers'] = 'Confirmar matrículas';
$string['confirmusers_desc'] = 'Os usuários nas linhas em cinza estão na lista de espera.';
$string['coursename'] = 'Curso';
$string['custom_label'] = 'Rótulo personalizado';
$string['defaultperiod'] = 'Duração padrão da matrícula';
$string['defaultperiod_desc'] = 'Tempo padrão de validade da matrícula. Se for zero, a duração da matrícula será ilimitada por padrão.';
$string['defaultperiod_help'] = 'Tempo padrão de validade da matrícula, contado a partir do momento em que o usuário é matriculado. Se desativado, a duração da matrícula será ilimitada por padrão.';
$string['defaultrole_desc'] = 'Papel atribuído ao usuário quando a solicitação de matrícula é aprovada.';
$string['editdescription'] = 'Descrição da área de texto';
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
$string['group'] = 'Atribuição de grupos';
$string['group_help'] = 'Você pode atribuir nenhum ou vários grupos. Os membros são adicionados quando a solicitação de matrícula é aprovada.';
$string['invalidformaction'] = 'Ação desconhecida solicitada para as matrículas selecionadas.';
$string['mailtoteacher_subject'] = 'Nova solicitação de matrícula!';
$string['maxenrolled'] = 'Número máximo de usuários matriculados';
$string['maxenrolled_help'] = 'Define o número máximo de usuários que podem solicitar matrícula neste curso. 0 significa sem limite.';
$string['maxenrolled_tip'] = '{$a->count} de {$a->max} vagas já preenchidas.';
$string['maxenrolledreached'] = 'O número máximo de usuários permitido ({$a}) já foi atingido.';
$string['messageprovider:application'] = 'Notificações de solicitação de matrícula em curso';
$string['messageprovider:cancelation'] = 'Notificações de cancelamento de solicitação de matrícula';
$string['messageprovider:confirmation'] = 'Notificações de confirmação de solicitação de matrícula';
$string['messageprovider:expiry_notification'] = 'Notificações de expiração de matrícula';
$string['messageprovider:waitinglist'] = 'Notificações de adiamento de solicitação de matrícula';
$string['newapplicationnotification'] = 'Há uma nova solicitação de matrícula aguardando análise.';
$string['newenrols'] = 'Permitir novas solicitações de matrícula no curso';
$string['newenrols_desc'] = 'Permitir, por padrão, que usuários solicitem matrícula em novas instâncias.';
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
$string['pluginname'] = 'Matrículas solicitadas';
$string['pluginname_desc'] = 'Com este plugin os usuários podem solicitar matrícula em um curso. Um professor ou gerente do site precisa aprovar a solicitação antes de o usuário ser matriculado.';
$string['privacy:applicationpath'] = 'Solicitação de matrícula';
$string['privacy:metadata:enrol_apply_applicationinfo'] = 'Informações enviadas junto com uma solicitação de matrícula em curso.';
$string['privacy:metadata:enrol_apply_applicationinfo:comment'] = 'O comentário escrito pelo usuário ao solicitar a matrícula no curso.';
$string['privacy:metadata:enrol_apply_applicationinfo:userenrolmentid'] = 'A matrícula à qual a solicitação pertence, que identifica o usuário solicitante e o curso.';
$string['profileoption'] = 'Campo de perfil a exibir na tabela';
$string['profileoption_desc'] = 'Um campo de perfil de texto adicional para exibir como coluna na fila de solicitações de matrícula.';
$string['selectapplicant'] = 'Selecionar {$a}';
$string['sendexpirynotificationstask'] = 'Envio de notificações de expiração de matrícula';
$string['show_extra_user_profile'] = 'Exibir campos de perfil adicionais na tela de matrícula';
$string['show_extra_user_profile_desc'] = 'Padrão para novas instâncias: coletar os campos de perfil personalizados no formulário de solicitação.';
$string['show_standard_user_profile'] = 'Exibir campos padrão do perfil na tela de matrícula';
$string['show_standard_user_profile_desc'] = 'Padrão para novas instâncias: coletar os campos padrão do perfil no formulário de solicitação.';
$string['status'] = 'Aceitar matrícula após aprovação';
$string['status_desc'] = 'Permitir o acesso ao curso a usuários matriculados internamente.';
$string['submitted_info'] = 'Informações da solicitação';
$string['syncenrolmentstask'] = 'Sincronização de matrículas expiradas';
$string['user_profile'] = 'Perfil do usuário';
$string['waitmail_desc'] = '';
$string['waitmail_heading'] = 'E-mail de lista de espera';
$string['waitmailcontent'] = 'Conteúdo do e-mail de lista de espera';
$string['waitmailcontent_desc'] = 'Use as marcações a seguir para substituir o conteúdo do e-mail por dados do Moodle.<br/>{firstname}: o nome do usuário; {content}: o nome do curso; {lastname}: o sobrenome do usuário; {username}: o nome de usuário do cadastro';
$string['waitmailsubject'] = 'Assunto do e-mail de lista de espera';
$string['waitmailsubject_desc'] = '';

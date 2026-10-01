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
 * Brazilian Portuguese strings.
 *
 * @package local_roleexplainer
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['adminshortcut'] = 'O usuário analisado é administrador do site e o Moodle retornou verdadeiro pelo atalho administrativo do parâmetro doanything.';
$string['aiexplanation'] = 'Explicação da IA';
$string['aisummary'] = 'Resumo';
$string['aiunavailable'] = 'A análise determinística foi concluída, mas a explicação da IA não pôde ser gerada: {$a}';
$string['allow'] = 'Permitir';
$string['allowed'] = 'Permitido';
$string['analyse'] = 'Analisar permissão';
$string['analysisdetails'] = 'Detalhes determinísticos';
$string['archetype'] = 'Arquétipo';
$string['assignmentsource'] = 'Origem da atribuição';
$string['assignroleslink'] = 'Atribuir roles';
$string['block'] = 'Bloco';
$string['cannotanalyseownsession'] = 'O estado da sessão atual impediu a conclusão segura desta análise.';
$string['capability'] = 'Capability';
$string['capabilitymissingreason'] = 'A capability não está registrada e, por isso, o Moodle trata a verificação como falsa.';
$string['capabilityname'] = 'Capability';
$string['context'] = 'Contexto';
$string['contextchain'] = 'Cadeia de contextos';
$string['contextid'] = 'ID do contexto';
$string['contextid_help'] = 'Informe o ID do contexto Moodle que será analisado. O contexto de sistema normalmente é 1. Contextos de categoria, curso, atividade e bloco são suportados.';
$string['contextidlabel'] = 'ID do contexto';
$string['contextinstance'] = 'ID da instância';
$string['contextlevel'] = 'Nível de contexto';
$string['contextlocked'] = 'O contexto está bloqueado e esta é uma capability de escrita; o Moodle negou o acesso antes da agregação normal das roles.';
$string['course'] = 'Curso';
$string['coursecategory'] = 'Categoria de curso';
$string['decisivefactors'] = 'Fatores decisivos';
$string['decisiverules'] = 'Regras decisivas';
$string['defaultuserrole'] = 'Role padrão de usuário autenticado';
$string['definition'] = 'Definição da role';
$string['denied'] = 'Negado';
$string['doanything'] = 'doanything';
$string['effective'] = 'Efetiva';
$string['effectivepermission'] = 'Permissão efetiva nesta role';
$string['explicitassignment'] = 'Atribuição explícita de role';
$string['frontpagerole'] = 'Role padrão da página inicial';
$string['guestrisky'] = 'Usuários convidados ou não autenticados não podem receber capabilities de escrita ou consideradas de risco.';
$string['guestrole'] = 'Role de convidado';
$string['inherited'] = 'Herdado';
$string['intro'] = 'Inspecione como o Moodle resolveu uma capability para um usuário em um contexto específico. O resultado final vem sempre de has_capability(); a IA apenas explica as evidências determinísticas.';
$string['invalidcontext'] = 'O contexto selecionado não existe.';
$string['invaliduser'] = 'O usuário selecionado não existe ou foi excluído.';
$string['links'] = 'Telas de permissões do Moodle';
$string['loginasrestricted'] = 'A sessão atual de "entrar como" restringe verificações de capability fora da subárvore de contexto permitida.';
$string['missingcapability'] = 'Esta capability não existe no registro de capabilities do Moodle.';
$string['mode'] = 'Modo da pergunta';
$string['modefull'] = 'Explicar o resultado';
$string['modewhycan'] = 'Por que este usuário pode?';
$string['modewhycannot'] = 'Por que este usuário não pode?';
$string['module'] = 'Módulo de atividade';
$string['no'] = 'Não';
$string['noexplicitroles'] = 'Nenhuma atribuição explícita de role foi encontrada nesta cadeia de contextos.';
$string['norule'] = 'Sem regra explícita neste contexto';
$string['officialresult'] = 'Resultado oficial do Moodle';
$string['othercontext'] = 'Outro contexto';
$string['override'] = 'Override';
$string['overridelink'] = 'Editar override desta role';
$string['permission'] = 'Permissão';
$string['permissionslink'] = 'Revisar permissões';
$string['pluginname'] = 'Explicador de permissões';
$string['prevent'] = 'Impedir';
$string['privacy:metadata'] = 'O Explicador de permissões não persiste dados das análises.';
$string['prohibit'] = 'Proibir';
$string['prohibitdecisive'] = 'Foi encontrada uma regra PROHIBIT. No Moodle, qualquer prohibit em qualquer role aplicável nega a capability.';
$string['role'] = 'Role';
$string['roleaggregatedallow'] = 'Ao menos uma role aplicável resultou em ALLOW e nenhuma role aplicável continha PROHIBIT.';
$string['roleaggregateddeny'] = 'Nenhuma role aplicável resultou em ALLOW, ou as regras efetivas mais próximas resultaram em PREVENT/herança.';
$string['rolechain'] = 'Avaliação das roles';
$string['roleexplainer:use'] = 'Usar o explicador de roles e permissões';
$string['roleid'] = 'ID da role';
$string['runtimeassignment'] = 'Atribuição de role em tempo de execução';
$string['source'] = 'Origem';
$string['suggestedchecks'] = 'Verificações sugeridas';
$string['switchedrole'] = 'Role assumida por troca de role';
$string['system'] = 'Sistema';
$string['targetuser'] = 'Usuário analisado';
$string['targetuseranonymous'] = 'Target user';
$string['targetuserlabel'] = 'Usuário analisado';
$string['unknown'] = 'Desconhecido';
$string['usercontext'] = 'Usuário';
$string['withoutadminshortcut'] = 'Resultado com doanything=false';
$string['yes'] = 'Sim';

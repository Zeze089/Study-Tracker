# Study Tracker

Study Tracker e um sistema web para registrar, acompanhar e analisar uma rotina de estudos. Ele foi criado para responder perguntas simples do dia a dia:

- Eu estudei hoje?
- Qual materia estudei?
- Quanto tempo estudei?
- Qual conteudo foi visto?
- Como esta minha consistencia no mes?
- Qual e minha sequencia atual de estudos?
- Quais materias receberam mais tempo?

O sistema usa autenticacao, separa os dados por usuario e oferece paginas para Dashboard, Calendario Mensal, Visao Anual, Materias, Metas e Relatorio Mensal.

## Stack do Projeto

- PHP 8.3
- Laravel 13
- Laravel Breeze com Blade
- Tailwind CSS
- Alpine.js
- Chart.js
- Vite
- PHPUnit
- Laravel Pint

## Funcionalidades Principais

### Autenticacao

O sistema possui fluxo de acesso com:

- Cadastro de usuario
- Login
- Logout
- Estrutura de verificacao de e-mail do Breeze
- Confirmacao de senha para fluxos sensiveis internos do Breeze
- Recuperacao de senha pelo fluxo de "esqueci minha senha"

Por decisao do projeto, nao existe area de perfil para o usuario alterar nome, e-mail ou senha depois de logado. Tambem nao existe link de perfil no menu.

### Dashboard

A pagina inicial autenticada e o Dashboard. Ela concentra os principais indicadores da rotina de estudos.

O Dashboard mostra:

- Status do dia atual
- Botao para registrar ou editar o estudo de hoje
- Materia mais estudada no mes
- Sequencia atual de estudos
- Dias estudados no mes
- Tempo total estudado no mes
- Percentual de consistencia mensal
- Grafico dos ultimos 7 dias
- Ranking de materias do mes
- Atividade recente com os ultimos registros

Quando um registro de estudo e salvo, o Dashboard pode atualizar os dados via endpoint JSON `/dashboard/stats`.

### Registro de Estudo

O registro de estudo e a unidade central do sistema. Cada usuario pode ter apenas um registro por data, mas esse registro pode conter varios itens de estudo.

Um registro guarda:

- Data do estudo
- Se estudou ou nao estudou
- Uma ou mais materias/areas estudadas
- Conteudo estudado em cada materia
- Tempo estudado em cada materia
- Observacao opcional

Se o usuario registrar novamente a mesma data, o sistema atualiza o registro existente em vez de criar duplicidade.

Regras importantes:

- Nao e permitido registrar datas futuras.
- Cada materia selecionada precisa pertencer ao usuario autenticado.
- Apenas materias ativas aparecem para novos registros.
- O tempo total somando todos os itens nao pode ultrapassar 24 horas no mesmo dia.
- O campo de conteudo estudado de cada item e opcional e aceita ate 255 caracteres.
- A observacao e opcional e aceita ate 2000 caracteres.

### Calendario Mensal

A pagina de Calendario Mensal mostra o mes em formato de calendario.

Cada dia pode aparecer como:

- Estudou
- Nao estudou
- Nao registrado
- Hoje
- Data futura bloqueada

O calendario permite navegar entre meses, voltar para o mes atual e selecionar mes/ano manualmente. Ao clicar em um dia permitido, o usuario pode registrar ou editar o estudo daquela data.

O calendario tambem mostra materia, conteudo e tempo do registro quando essas informacoes existem.

### Materias

A area de Materias permite gerenciar as areas de estudo do usuario.

O usuario pode:

- Criar uma nova materia
- Editar nome, cor e status
- Ativar ou desativar materia
- Excluir uma materia sem historico

Regras das materias:

- O nome da materia precisa ser unico para cada usuario.
- Usuarios diferentes podem ter materias com o mesmo nome.
- A cor deve estar no formato hexadecimal, como `#14b8a6`.
- Materias inativas continuam aparecendo no historico antigo.
- Materias inativas nao aparecem para novos registros.
- Uma materia com registros vinculados nao pode ser excluida; nesse caso, o correto e desativar para preservar o historico.

O sistema ja vem com um seeder de materias iniciais, como Laravel, PHP, JavaScript, Vue, React, Redes, Pentest, Linux, Docker e Banco de Dados.

### Metas

A area de Metas exibe metas ativas de consistencia.

Uma meta possui:

- Tipo semanal ou mensal
- Quantidade alvo de dias estudados
- Data inicial
- Data final
- Status ativo/inativo

O progresso de uma meta e calculado comparando os dias estudados dentro do periodo da meta com a quantidade alvo definida.

Atualmente a estrutura de dominio e exibicao de metas esta preparada; a pagina lista as metas ativas e mostra progresso, porcentagem e dias restantes.

### Relatorio Mensal

O Relatorio Mensal resume o desempenho do mes atual.

Ele mostra:

- Dias estudados
- Tempo total
- Consistencia

Os calculos sao baseados apenas no periodo decorrido do mes, ate a data atual do usuario.

### Visao Anual

A pagina Ano mostra indicadores acumulados no ano:

- Dias estudados
- Horas estudadas
- Sequencia atual
- Maior sequencia

Essa pagina usa os registros do usuario autenticado e respeita o timezone configurado para o usuario.

## Como os Calculos Funcionam

### Consistencia

A consistencia e calculada assim:

```text
dias estudados / dias decorridos do periodo * 100
```

Exemplo: se ja passaram 10 dias no mes e o usuario estudou em 7 deles, a consistencia sera 70%.

### Tempo Total

O formulario aceita horas e minutos separadamente, mas o banco armazena tudo como minutos.

Exemplo:

```text
2 horas e 30 minutos = 150 minutos
```

Na interface, o sistema formata o valor novamente como `2h 30min`.

### Sequencia Atual

A sequencia atual conta dias estudados consecutivos ate hoje ou ate ontem.

Regras:

- Se hoje ainda nao tem registro, isso nao quebra a sequencia.
- Se hoje estiver registrado como "Nao estudou", a sequencia atual vira 0.
- Registros ausentes ou marcados como "Nao estudou" interrompem a sequencia.

### Maior Sequencia

A maior sequencia percorre todos os registros marcados como estudados e encontra o maior bloco de datas consecutivas.

## Modelo de Dados

### users

Tabela padrao de usuarios, com campos adicionais de timezone.

Campos importantes:

- `name`
- `email`
- `password`
- `timezone`
- `email_verified_at`

Relacionamentos:

- Um usuario possui muitas materias.
- Um usuario possui muitos registros de estudo.
- Um usuario possui muitas metas.

### subjects

Representa uma materia ou area de estudo.

Campos:

- `user_id`
- `name`
- `color`
- `active`

Indices e regras:

- Nome unico por usuario: `user_id + name`
- Indice para busca por usuario e status ativo
- Ao excluir um usuario, suas materias sao excluidas em cascata

### study_records

Representa o dia registrado pelo usuario.

Campos:

- `user_id`
- `study_date`
- `studied`
- `subject_id`
- `content`
- `minutes`
- `notes`

Indices e regras:

- Apenas um registro por usuario/data: `user_id + study_date`
- `subject_id`, `content` e `minutes` permanecem como compatibilidade e resumo do primeiro item/total do dia
- Existem indices para consultas por usuario, data, status e materia

### study_record_items

Representa cada materia/conteudo estudado dentro de um registro diario.

Campos:

- `study_record_id`
- `subject_id`
- `content`
- `minutes`
- `position`

Indices e regras:

- Um registro diario pode ter varios itens.
- Cada item pode ter uma materia propria.
- Cada item pode ter um conteudo proprio.
- Cada item pode ter um tempo proprio em minutos.
- A soma dos minutos dos itens alimenta o tempo total do dia.
- Se uma materia for removida, o `subject_id` do item fica nulo para preservar o historico.

### goals

Representa metas de consistencia.

Campos:

- `user_id`
- `type`
- `target_days`
- `starts_on`
- `ends_on`
- `active`

Tipos:

- `weekly`
- `monthly`

## Rotas Principais

### Paginas autenticadas

| Metodo | Rota | Nome | Funcao |
| --- | --- | --- | --- |
| GET | `/dashboard` | `dashboard` | Mostra o painel principal |
| GET | `/dashboard/stats` | `dashboard.stats` | Retorna estatisticas do Dashboard em JSON |
| GET | `/calendar` | `calendar.index` | Mostra o calendario mensal |
| GET | `/calendario` | `calendar.legacy` | Alias do calendario |
| GET | `/ano` | `year.index` | Mostra a visao anual |
| GET | `/materias` | `subjects.index` | Lista e gerencia materias |
| GET | `/subjects` | `subjects.legacy` | Alias legado de materias |
| GET | `/metas` | `goals.index` | Mostra metas ativas |
| GET | `/relatorios/mensal` | `reports.monthly` | Mostra resumo mensal |

### Acoes autenticadas

| Metodo | Rota | Nome | Funcao |
| --- | --- | --- | --- |
| POST | `/subjects` | `subjects.store` | Cria materia |
| PUT | `/subjects/{subject}` | `subjects.update` | Atualiza materia |
| DELETE | `/subjects/{subject}` | `subjects.destroy` | Exclui materia sem historico |
| POST | `/study-records` | `study-records.store` | Cria ou atualiza registro por data |
| PUT | `/study-records/{studyRecord}` | `study-records.update` | Atualiza registro existente |
| POST | `/logout` | `logout` | Encerra sessao |

### Autenticacao

| Metodo | Rota | Funcao |
| --- | --- | --- |
| GET | `/login` | Tela de login |
| POST | `/login` | Autentica usuario |
| GET | `/register` | Tela de cadastro |
| POST | `/register` | Cria usuario |
| GET | `/forgot-password` | Solicita recuperacao de senha |
| POST | `/forgot-password` | Envia link de recuperacao |
| GET | `/reset-password/{token}` | Tela para redefinir senha |
| POST | `/reset-password` | Redefine senha |
| GET | `/confirm-password` | Tela de confirmacao de senha |
| POST | `/confirm-password` | Confirma senha do usuario autenticado |
| GET | `/verify-email` | Aviso de verificacao de e-mail |
| GET | `/verify-email/{id}/{hash}` | Confirma e-mail por link assinado |
| POST | `/email/verification-notification` | Reenvia verificacao |

## Estrutura de Arquivos

### Controllers

- `DashboardController`: entrega os dados iniciais do Dashboard.
- `DashboardStatsController`: retorna os dados atualizados do Dashboard em JSON.
- `CalendarController`: monta o calendario mensal e os dados do dia atual.
- `YearlyOverviewController`: monta os indicadores anuais.
- `MonthlyReportController`: monta o resumo mensal.
- `SubjectController`: cria, lista, atualiza e exclui materias.
- `StudyRecordController`: cria/atualiza registros de estudo.
- `GoalController`: lista metas ativas e progresso.

### Services

- `DashboardService`: concentra dados do Dashboard.
- `StudyStatsService`: calcula resumo mensal, anual, ranking de materias, grafico e atividade recente.
- `CalendarService`: monta os dias/semanas do calendario mensal.
- `StreakService`: calcula sequencia atual e maior sequencia.
- `GoalProgressService`: calcula progresso das metas.
- `StudyRecordService`: cria ou atualiza registros de estudo.
- `StudyRecordFormatter`: serializa registros e formata duracao.

### Views

- `resources/views/dashboard.blade.php`: painel principal.
- `resources/views/calendar/index.blade.php`: calendario mensal.
- `resources/views/year/index.blade.php`: visao anual.
- `resources/views/subjects/index.blade.php`: gerenciamento de materias.
- `resources/views/goals/index.blade.php`: metas.
- `resources/views/reports/monthly.blade.php`: resumo mensal.
- `resources/views/components/study-record-panel.blade.php`: modal/painel de registro de estudo.
- `resources/views/layouts/navigation.blade.php`: navegacao do sistema.
- `resources/views/layouts/guest.blade.php`: layout de login/cadastro.

### Frontend

- `resources/js/app.js`: Alpine.js, logica interativa dos modais, calendario, materias e Dashboard.
- `resources/css/app.css`: entrada de estilos Tailwind.
- `public/brand-mark.svg`: marca do sistema.
- `public/favicon.svg` e `public/favicon.ico`: favicon.

## Instalacao Local

### Requisitos

- PHP 8.3 ou superior
- Composer
- Node.js e npm
- MySQL ou MariaDB
- Servidor local, como Laragon

### Passo a passo

1. Instale as dependencias PHP:

```bash
composer install
```

2. Instale as dependencias JavaScript:

```bash
npm install
```

3. Copie o arquivo de ambiente:

```bash
copy .env.example .env
```

No PowerShell, tambem pode usar:

```powershell
Copy-Item .env.example .env
```

4. Gere a chave da aplicacao:

```bash
php artisan key:generate
```

5. Configure o banco no `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=study_tracker
DB_USERNAME=root
DB_PASSWORD=
```

6. Rode as migrations:

```bash
php artisan migrate
```

7. Opcionalmente, rode os seeders:

```bash
php artisan db:seed
```

O seeder cria um usuario de teste:

```text
E-mail: test@example.com
Senha: password
```

Tambem cria materias iniciais para esse usuario.

8. Rode o servidor de desenvolvimento:

```bash
php artisan serve
```

9. Em outro terminal, rode o Vite:

```bash
npm run dev
```

Se estiver usando Laragon com virtual host, a URL local esperada no `.env.example` e:

```text
http://study-tracker.test
```

## Build de Producao

Para gerar os assets finais:

```bash
npm run build
```

O Vite gera os arquivos em `public/build`.

## Testes e Qualidade

### Rodar todos os testes

```bash
php artisan test
```

### Rodar testes em formato compacto

```bash
php artisan test --compact
```

### Rodar um arquivo especifico

```bash
php artisan test --compact tests/Feature/SubjectControllerTest.php
```

### Formatar PHP com Pint

```bash
vendor/bin/pint --format agent
```

No Windows:

```powershell
vendor\bin\pint --format agent
```

## Decisoes Importantes do Sistema

- O sistema nao possui pagina de perfil.
- O usuario nao altera e-mail ou senha por uma area interna de conta.
- O login nao mostra "lembrar-me" nem link de "esqueci minha senha" na tela principal.
- A recuperacao de senha ainda existe por rota propria, mas nao aparece no card principal de login.
- Cada usuario enxerga apenas seus proprios dados.
- Materias com historico nao sao excluidas para nao quebrar relatorios antigos.
- Materias inativas preservam historico, mas nao podem ser usadas em novos registros.
- Um dia pode ser registrado como "estudou" ou "nao estudou".
- Dias futuros nao podem receber registros.

## Fluxo de Uso Recomendado

1. Criar ou acessar uma conta.
2. Conferir as materias iniciais ou cadastrar novas materias.
3. No Dashboard, clicar em "Registrar estudo".
4. Informar se estudou e adicionar uma ou mais materias, com conteudo e tempo de cada uma.
5. Usar o Calendario para preencher ou editar dias anteriores.
6. Acompanhar consistencia, sequencias e tempo total no Dashboard.
7. Usar Relatorio Mensal e Ano para acompanhar evolucao.
8. Desativar materias antigas quando nao forem mais usadas.

## Manutencao

Ao alterar regras de estudo, materias, metas ou estatisticas, verifique:

- Form Requests em `app/Http/Requests`
- Policies em `app/Policies`
- Services em `app/Services`
- Testes em `tests/Feature`
- Componentes Blade em `resources/views/components`
- Logica Alpine em `resources/js/app.js`

Depois de qualquer alteracao relevante, rode:

```bash
php artisan test --compact
npm run build
```

Se arquivos PHP forem alterados, rode tambem:

```bash
vendor/bin/pint --format agent
```

## Status Atual

O sistema esta estruturado como uma aplicacao Laravel monolitica com Blade, Alpine e Tailwind. Ele ja possui autenticacao, gerenciamento de materias, registro diario de estudos, calendario mensal, metricas de Dashboard, metas, relatorio mensal, visao anual, marca visual e favicon.

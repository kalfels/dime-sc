# Sistema DIME-SC - Gestão de Declarações Fiscais

## 📋 Visão Geral

O **Sistema DIME-SC** é uma aplicação web completa para gestão de declarações fiscais do estado de Santa Catarina, desenvolvida em PHP com arquitetura MVC. O sistema permite o gerenciamento completo de declarações DIME (Declaração de Informações Mensais do ICMS) e DCIP (Declaração de Crédito do ICMS Próprio), com controle de acesso baseado em regras (roles) e interface responsiva.

### 🎯 Principais Funcionalidades

- **👥 Gestão de Usuários**: Controle de usuários com diferentes níveis de acesso (admin/operador)
- **🏢 Gestão de Contabilistas**: Cadastro e associação de contabilistas às empresas
- **🏭 Gestão de Empresas**: Controle completo de empresas com validações de CNPJ e Inscrição Estadual
- **📊 Declarações DIME**: Criação, edição e geração de arquivos DIME conforme especificações da SEF/SC
- **💰 Sistema DCIP**: Gestão completa de créditos do ICMS Próprio conforme Anexo III
- **📁 Geração de Arquivos Fiscais**: Geração automática de arquivos TXT/CSV conforme layouts oficiais
- **📈 Dashboard Analítico**: Painel com estatísticas e filtros avançados
- **📧 Sistema de E-mail**: Notificações automáticas via SMTP (Zoho Mail)
- **🔍 Auditoria**: Logs completos de todas as ações do sistema
- **🔐 Recuperação de Senha**: Sistema seguro de recuperação via e-mail

---

## 🏗️ Arquitetura do Sistema

### 📁 Estrutura de Diretórios
```
dime/
├── public/                 # Interface pública da aplicação
│   ├── _header.php        # Template de cabeçalho
│   ├── _footer.php        # Template de rodapé
│   ├── index.php          # Página de login
│   ├── dashboard.php      # Dashboard principal
│   ├── seguranca.php      # Gestão de senha e segurança
│   ├── setup_conta.php    # Configuração inicial de conta
│   ├── recuperar-senha.php # Recuperação de senha
│   ├── inserir_dime.php   # Formulário de criação DIME
│   ├── editar_dime.php    # Edição de declarações DIME
│   ├── gerar_arquivo_dime.php # Gerador de arquivos DIME
│   ├── listar_dcip.php    # Listagem de declarações DCIP
│   ├── gerar_arquivo_dcip.php # Gerador de arquivos DCIP
│   ├── gerenciar_empresas.php # Gestão de empresas
│   ├── gerenciar_contabilistas.php # Gestão de contabilistas
│   ├── gerenciar_usuarios.php # Gestão de usuários
│   ├── ajax_*.php         # Handlers AJAX
│   └── includes/          # Componentes reutilizáveis
├── src/                   # Código fonte da aplicação
│   ├── app_classes.php    # Classes principais (MVC)
│   └── Services/          # Serviços da aplicação
│       └── EmailService.php # Serviço de e-mail
├── database/              # Esquema do banco de dados
│   ├── database_schema.sql # Script completo do banco
│   └── add_password_recovery_fields_migration.sql # Migração campos senha
├── docs/                  # Documentação completa
├── vendor/                # Dependências (Composer)
└── composer.json          # Configuração do Composer
```

### 🛠️ Tecnologias Utilizadas

- **Backend**: PHP 7.4+
- **Banco de Dados**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5
- **E-mail**: PHPMailer com SMTP (Zoho Mail)
- **Gerenciamento de Dependências**: Composer
- **Ícones**: Bootstrap Icons
- **Charts/Gráficos**: Integração preparada para bibliotecas de gráficos

### 📐 Padrões de Desenvolvimento

- **Arquitetura MVC**: Separação clara entre Model, View e Controller
- **PSR-4 Autoloading**: Carregamento automático de classes via Composer
- **PDO Database Access**: Acesso seguro ao banco de dados
- **Bootstrap 5**: Interface responsiva e moderna
- **Prepared Statements**: Prevenção contra SQL Injection
- **Session Management**: Controle de sessão seguro
- **Role-Based Access Control**: Controle de acesso baseado em roles

---

## 🚀 Instalação e Configuração

### 📋 Pré-requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Composer (para gerenciamento de dependências)
- Servidor web (Nginx/Apache) com suporte a .htaccess

### 📦 Instalação

1. **Clone o repositório**:
   ```bash
   git clone https://github.com/kalfels/dime-sc.git
   cd dime-sc
   ```

2. **Instale as dependências**:
   ```bash
   composer install
   ```

3. **Configure o banco de dados**:
   - Crie um banco de dados MySQL chamado `dime`
   - Execute o arquivo único: `database/install_complete.sql`
   - Este arquivo contém todas as tabelas, migrações e dados iniciais
   - Configure as credenciais no arquivo `src/app_classes.php`

4. **Campos adicionais na tabela usuarios**:
   - `primeiro_acesso` (TINYINT): Flag para controle de primeiro acesso
   - `status_pagamento` (ENUM): Controle de bloqueio por pendências financeiras
   - `token_redefinicao` (VARCHAR): Token para recuperação de senha
   - `token_expiracao` (TIMESTAMP): Expiração do token de recuperação

4. **Configure o ambiente**:
   - Atualize as configurações de banco em `src/app_classes.php`
   - Configure as credenciais de e-mail em `src/Services/EmailService.php`
   - Certifique-se de que a pasta `public/` seja o document root

5. **Configure permissões**:
   ```bash
   chmod 755 public/
   chmod 644 public/*.php
   ```

### ⚙️ Configuração do Banco de Dados

As credenciais do banco devem ser configuradas em `src/app_classes.php`:

```php
private $dbConfig = [
    'host' => 'localhost',
    'dbname' => 'dime',
    'user' => 'seu_usuario',
    'pass' => 'sua_senha',
    'charset' => 'utf8mb4'
];
```

### 📧 Configuração do E-mail

Configure o serviço de e-mail em `src/Services/EmailService.php`:

```php
private $smtpHost = 'smtp.zoho.com';
private $smtpPort = 587;
private $smtpUsername = 'seu-email@seudominio.com';
private $smtpPassword = 'sua-senha-app';
```

---

## 📊 Funcionalidades por Módulo

### 1. 🔐 Sistema de Controle de Acesso

#### 👤 Tipos de Usuário
- **Admin**: Acesso completo ao sistema
- **Operador**: Acesso limitado, vinculado a contabilista

#### 🚪 Sistema de Primeiro Acesso
- **Verificação Automática**: Sistema identifica automaticamente usuários em primeiro acesso
- **Configuração Completa**: Página unificada para cadastro de contabilista e definição de senha
- **Validação de Status**: Verifica status ativo e status de pagamento antes do login
- **Redirecionamento Inteligente**: Admin vai direto ao dashboard, operadores fazem configuração inicial

#### 🔑 Recuperação de Senha
- **Tokens Seguros**: Sistema de tokens com expiração automática (1 hora)
- **Validação de E-mail**: Verificação obrigatória do e-mail cadastrado
- **Segurança**: Hash seguro e expiração de tokens
- **Templates HTML**: E-mails formatados profissionalmente

### 2. 🏢 Gestão de Contabilistas

#### 👨‍💼 Funcionalidades por Tipo de Usuário

**Administrador:**
- Menu "Contabilistas" - Acesso completo
- Pode adicionar, editar e excluir contabilistas
- Interface completa com lista de todos os contabilistas

**Operador:**
- Menu "Meus Dados" - Acesso restrito
- Só pode editar seus próprios dados
- Redirecionamento automático baseado no status

#### 🔒 Segurança
- CPF único no sistema
- E-mail único no sistema
- Validação de dados obrigatórios
- Logs de auditoria completos

### 3. 🏭 Sistema DIME

#### 📋 Quadros DIME Implementados
- **Quadro 00**: Informações da empresa
- **Quadro 01**: Entradas de mercadorias/serviços
- **Quadro 02**: Saídas de mercadorias/serviços
- **Quadro 04**: Débitos do ICMS
- **Quadro 05**: Créditos do ICMS
- **Quadro 11**: Substituição tributária
- **Quadro 12**: Pagamentos do ICMS
- **Quadro 48**: Valor adicionado por município

#### 📄 Geração de Arquivos
- Geração automática de arquivos TXT
- Conformidade com layout da SEF/SC
- Download direto ou envio por e-mail
- Validação de integridade do arquivo

### 4. 💰 Sistema DCIP

#### 📊 Funcionalidades DCIP
- **Geração de Arquivos**: Arquivos DCIP conforme Anexo III
- **Gestão de Créditos**: Controle detalhado de créditos do ICMS
- **Utilização de Créditos**: Registro de utilização por período
- **Cálculos Automáticos**: Saldos e validações em tempo real
- **Listagem e Filtros**: Interface completa para gestão

#### 📋 Registros DCIP Implementados
- **Registro 040**: Identificação do contribuinte
- **Registro 050**: Período de apuração
- **Registro 060**: Informações do contabilista
- **Registro 070**: Créditos do ICMS Próprio
- **Registro 080**: Detalhamento dos créditos
- **Registro 090**: Utilização dos créditos
- **Registro 100**: Saldo de créditos
- **Registro 110**: Informações complementares
- **Registro 140**: Totalizadores
- **Registro 150**: Observações
- **Registro 900**: Fim do arquivo

### 5. 📈 Dashboard e Relatórios

#### 📊 Dashboard Principal
- **Estatísticas Visuais**: Cards com métricas principais
- **Ações Rápidas**: Botões para operações comuns
- **Filtros Avançados**: Por empresa, período, status
- **Tabela Interativa**: Listagem de declarações com ações

### 6. 📧 Sistema de E-mail

#### 📨 Funcionalidades
- **Envio Automático**: Notificações de sistema
- **Templates HTML**: E-mails formatados profissionalmente
- **Recuperação de Senha**: Tokens seguros com expiração (1 hora)
- **Boas-vindas**: E-mails de cadastro automático
- **SMTP Seguro**: Configuração com Zoho Mail

#### 🔐 Recuperação de Senha Detalhada
- **Página dedicada**: `recuperar-senha.php`
- **Validação obrigatória**: E-mail deve existir no sistema
- **Tokens únicos**: Gerados com `bin2hex(random_bytes(32))`
- **Expiração automática**: 1 hora após geração
- **Limpeza automática**: Tokens expirados são removidos
- **Segurança**: Uso de `hash_equals()` para comparação segura
- **Logs completos**: Registro de todas as tentativas

#### 💰 Controle de Pendências Financeiras
- **Login permitido**: Operadores com `status_pagamento = 'bloqueado'` podem fazer login
- **Aviso visual**: Banner amarelo com informações sobre pendências
- **Navegação limitada**: Bloqueio de geração de DIME e DCIP
- **Interface adaptativa**: Botões desabilitados com ícones de cadeado
- **Overlay de bloqueio**: Formulários com sobreposição visual quando restritos

#### 📧 Configuração SMTP
- **Provedor**: Zoho Mail
- **Protocolo**: SMTP com STARTTLS
- **Porta**: 587
- **Autenticação**: Credenciais de aplicativo
- **Templates**: Layout responsivo com Bootstrap

---

## 🗄️ Estrutura do Banco de Dados

### 📋 Tabelas Principais

#### Sistema Core
- **`usuarios`**: Controle de usuários do sistema
- **`contabilistas`**: Cadastro de contabilistas
- **`empresas`**: Cadastro de empresas
- **`system_logs`**: Logs de auditoria

#### Referenciais
- **`t_cfops`**: Códigos Fiscais de Operações e Prestações (CFOP)
- **`t_municipios`**: Municípios de Santa Catarina
- **`t_codigos_receita`**: Códigos de receita do ICMS
- **`t_classes_vencimento`**: Classes de vencimento

#### DIME
- **`dime_declaracoes`**: Declarações DIME principais
- **`dime_manual_versoes`**: Versões do manual DIME
- **`dime_quadro_00`**: Informações iniciais da empresa
- **`dime_quadro_01-48`**: Quadros específicos da declaração

#### DCIP
- **`dcip_declaracoes`**: Declarações DCIP principais
- **`dcip_creditos`**: Detalhes dos créditos
- **`dcip_utilizacao_creditos`**: Utilização dos créditos

---

## 🔒 Segurança

### 🛡️ Medidas Implementadas

#### Autenticação
- Hash de senha com `password_hash()`
- Sessões seguras com regeneração
- Controle de timeout de sessão
- Bloqueio de conta por tentativas falhidas

#### Autorização
- Role-Based Access Control (RBAC)
- Controle de acesso por página
- Validação de permissões em operações críticas
- Logs de auditoria completos

#### Dados
- Prepared Statements contra SQL Injection
- Validação de entrada em todos os formulários
- Sanitização de dados de saída
- Controle de uploads de arquivos

#### Comunicação
- HTTPS obrigatório em produção
- Certificados SSL válidos
- Headers de segurança (CSP, HSTS)
- Rate limiting em endpoints críticos

---

## 📋 API e Integrações

### 🏗️ Estrutura de Classes

#### Core Classes
```php
// Conexão com banco de dados
App\Core\DBConnection

// Modelo base com CRUD
App\Models\Model

// Serviço de e-mail
App\Services\EmailService
```

#### Modelos Específicos
```php
App\Models\Usuario
App\Models\Contabilista
App\Models\Empresa
App\Models\DimeDeclaracao
App\Models\DcipDeclaracao
```

### 🔧 Métodos CRUD

Todos os modelos herdam métodos padrão:
- `all()`: Buscar todos os registros
- `find($id)`: Buscar por ID
- `where($field, $value)`: Buscar com condição
- `save()`: Salvar (insert/update automático)
- `delete()`: Excluir registro

---

## 🚀 Próximas Implementações

- **API REST** para integrações externas
- **Sistema de notificações em tempo real**
- **Relatórios avançados com gráficos**
- **Mobile app companion**
- **Integração com sistemas contábeis**
- **Dashboard com gráficos interativos**

---

## 🆕 Últimas Atualizações e Melhorias

### ✅ Sistema de Login Aprimorado
- **Primeiro Acesso**: Sistema automático de detecção e configuração inicial
- **Setup Unificado**: Página `setup_conta.php` para cadastro de contabilista + senha
- **Redirecionamento Inteligente**: Baseado no perfil e status do usuário
- **Validação em Cascata**: Verificações de status ativo, pagamento e primeiro acesso
- **Correção de Loops**: Resolvidos conflitos de redirecionamento

### ✅ Gestão de Contabilistas por Roles
- **Interface Diferenciada**: Admin vê lista completa, operador vê apenas seus dados
- **Menu Adaptativo**: "Contabilistas" para admin, "Meus Dados" para operador
- **Redirecionamento Automático**: Baseado no status de vínculo contabilista-usuário
- **Controle de Permissões**: Validações rigorosas de propriedade dos dados

### ✅ Sistema de Segurança Reforçado
- **Página Dedicada**: `seguranca.php` para gestão de senha
- **Validação Robusta**: Senha atual obrigatória, força da senha, confirmação
- **Logs de Auditoria**: Registro completo de alterações de senha
- **Proteções Anti-Bypass**: Múltiplas camadas de validação

### ✅ Recuperação de Senha Completa
- **Sistema de Tokens**: Geração segura com `bin2hex(random_bytes(32))`
- **Expiração Automática**: Tokens válidos por 1 hora
- **Validação Segura**: Uso de `hash_equals()` para comparação de tokens
- **Templates HTML**: E-mails responsivos e profissionais
- **Limpeza Automática**: Remoção de tokens expirados

### ✅ Estrutura de Banco de Dados Expandida
- **Novos Campos**: `primeiro_acesso`, `status_pagamento`, `token_redefinicao`, `token_expiracao`
- **Migrações Automáticas**: Scripts para atualização incremental
- **Índices de Performance**: Otimização para consultas frequentes
- **Integridade Referencial**: Constraints e relacionamentos mantidos

### ✅ Controle de Pendências Financeiras
- **Login flexível**: Operadores com pendências podem acessar o sistema
- **Aviso inteligente**: Banner visual informa sobre restrições ativas
- **Bloqueio funcional**: Geração de DIME e DCIP restrita para usuários bloqueados
- **Interface responsiva**: Botões e formulários adaptam-se ao status do usuário
- **Experiência preservada**: Outras funcionalidades permanecem acessíveis

---

## 📞 Suporte e Desenvolvimento

### 🛠️ Ambiente de Desenvolvimento
- PHP 7.4+ com Xdebug
- MySQL 5.7+ com InnoDB
- Nginx com mod_rewrite
- Git para controle de versão

### 📚 Documentação Adicional
- `database/install_complete.sql`: **ARQUIVO ÚNICO** para instalação completa do banco
- `backup/`: Arquivos de teste/desenvolvimento removidos de produção
- Código fonte comentado em `src/` com documentação inline

### 👥 Contato
**Desenvolvido por**: Bitserv - Luis Fernando Kalfels
**Contato**: luis@bitserv.com.br

---

**Versão**: 1.0.0
**Última atualização**: Dezembro 2024
**Compatibilidade**: PHP 7.4+, MySQL 5.7+

---

## 📦 Consolidação SQL (Instalação Única)

- **Arquivo único**: `database/install_complete.sql` realiza a criação completa do schema, dados referenciais e migrações.
- **Transacional**: inicia transação e finaliza com `COMMIT`.
- **Idempotente**: uso de `IF NOT EXISTS`/`INSERT IGNORE` quando aplicável para evitar erros em reexecuções.

### Conteúdo Consolidado
- **Core**: `usuarios`, `contabilistas`, `empresas`, `system_logs` e índices essenciais.
- **Referenciais**: `t_cfops`, `t_municipios`, `t_codigos_receita`, `t_classes_vencimento`.
- **DIME Base**: `dime_manual_versoes`, `dime_declaracoes`.
- **Quadros DIME Atualizados**:
  - `dime_quadro_04` reestruturado conforme Manual DIME-SEF 153/12 (itens 010, 020, 030, 040, 045, 050, 060, 065, 070, 990).
  - `dime_quadro_05` expandido com itens 010…160 + `item_990_subtotal_creditos` (mantidas colunas legadas para compatibilidade).
  - `dime_quadro_09` criado com estrutura detalhada de débitos/créditos/ajustes e saldos.
  - `dime_quadro_10` criado (Débitos Específicos) com totalização `item_990`.
  - `dime_quadro_11` mantido conforme definição de ST (recriado/atualizado por migrações dedicadas no repositório).
  - `dime_quadro_14` criado (Crédito Presumido).
  - `dime_quadro_41` criado (Créditos Acumulados).
  - `dime_quadro_46` criado (Regimes/Autorizações Especiais).
  - `dime_quadro_49` criado (Entradas por UF).
  - `dime_quadro_50` criado (Saídas por UF) com índices `idx_quadro50_uf`, `idx_quadro50_created`, `idx_quadro50_updated`.
- **Configuração por Empresa**: `dime_quadro_empresas` com vínculos padrão por empresa (quadros ativos por default).
- **DCIP**: `dcip_declaracoes`, `dcip_creditos`, `dcip_utilizacao_creditos` e índices.

### Migrações incorporadas no install
- Campos adicionais em `usuarios`: `primeiro_acesso`, `status_pagamento`, `token_redefinicao`, `token_expiracao`, `acesso_dcip` + índices relacionados.
- Dados iniciais: usuário admin, versões do manual, CFOPs, códigos de receita, classes de vencimento, municípios SC (amostra).
- Índices de performance adicionais (`idx_*`) para consultas frequentes.

### Observações de Ordem/Dependências
- As tabelas base (`dime_declaracoes`, referenciais e core) são criadas antes dos Quadros que possuem FKs.
- Estruturas duplicadas foram evitadas; definições de quadros aparecem após DCIP para garantir que FKs existam.

### Como aplicar
1. Crie o banco (ou deixe o install criar): `CREATE DATABASE dime;`
2. Execute `database/install_complete.sql` no MySQL/MariaDB.
3. Verifique com as consultas da seção "Verificação Final" ao final do script.


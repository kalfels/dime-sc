# Sistema DIME-SC - Gestão de Declarações Fiscais

## 📖 Documentação Oficial

A documentação completa e atualizada está localizada em:

➡️ **[docs/README.md](docs/README.md)** - **DOCUMENTAÇÃO OFICIAL COMPLETA**

---

## 🚀 Instalação Rápida

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
   ```bash
   # Execute apenas este arquivo único:
   mysql -u seu_usuario -p < database/install_complete.sql
   ```

4. **Configure as credenciais**:
   - Copie `.env_example` para `.env` e ajuste as credenciais de banco e e-mail
   - Não edite mais `src/app_classes.php` para segredos

5. **Acesse**: `http://localhost`

---

## 📋 Funcionalidades Principais

- **👥 Gestão de Usuários**: Admin e operadores com permissões diferenciadas
- **🏢 Contabilistas**: Gestão por roles (admin vê todos, operador vê apenas seus dados)
- **🏭 Empresas**: Cadastro completo com validações CNPJ/Inscrição Estadual
- **📊 Sistema DIME**: Declarações completas conforme SEF/SC
- **💰 Sistema DCIP**: Gestão de créditos do ICMS conforme Anexo III
- **📧 Recuperação de Senha**: Sistema seguro com tokens
- **📈 Dashboard**: Estatísticas e filtros avançados

---

## 🔐 Primeiro Acesso

- **Admin**: `admin@dime-sc.com` / `password`
- **Operador**: Precisa configurar primeiro acesso após cadastro

---

## 📚 Para Mais Informações

Consulte a **[documentação completa](docs/README.md)** para:
- Instalação detalhada passo-a-passo
- Configuração avançada de ambiente
- Funcionalidades específicas do sistema
- Estrutura completa do banco de dados
- Guia de desenvolvimento e manutenção
- Solução de problemas comuns

---

**Desenvolvido por**: Bitserv - Luis Fernando Kalfels
**Contato**: luis@bitserv.com.br
**Versão**: 1.0.0

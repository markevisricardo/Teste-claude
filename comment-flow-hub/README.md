# Comment Flow Hub

Servidor central para oferecer o **Comment Flow** a clientes no estilo ManyChat. Um único app da Meta, o seu, atende todos os sites: o cliente só instala o plugin, cola a chave de licença e clica em **Conectar com o Instagram**. Ele não cria nada na Meta.

```
                    ┌──────────── Meta (Instagram) ────────────┐
                    │  login OAuth            webhooks          │
                    └──────┬───────────────────────┬────────────┘
                           ▼                       ▼
            ┌──────────── Comment Flow Hub ─────────────────────┐
            │ app central · licenças · repasse com novas tentativas │
            └──────┬────────────────┬────────────────┬──────────┘
                   ▼                ▼                ▼
             site cliente A   site cliente B   site cliente C   (plugin Comment Flow, modo licença)
```

- **Login:** o hub faz o OAuth com o app central e entrega o token ao site do cliente por uma chamada servidor a servidor, sem passar pelo navegador. O hub **não guarda** tokens.
- **Webhooks:** a Meta envia tudo para o hub, que separa por conta e repassa ao site da licença. Se o site estiver fora do ar, o hub tenta de novo depois de 1, 5, 15, 60 e 180 minutos.
- **Segurança:** cada site recebe um segredo próprio na ativação. Toda chamada entre o hub e o site é assinada (HMAC-SHA256) e tem carimbo de tempo, o que impede reaproveitar requisições antigas.
- **Licenças:** são criadas, desativadas, liberadas para outro site ou excluídas no painel. Uma licença desativada deixa de receber eventos na hora.

## Instalação

1. Crie o WordPress do hub num subdomínio só para ele, por exemplo `commentflow.aceleradordeclinicas.com.br`:
   - com **HTTPS**;
   - com **links permanentes** em qualquer opção diferente de "Simples";
   - de preferência com o plugin **WP Crontrol** ou um cron real no servidor, para as novas tentativas rodarem no horário.
2. Instale o `comment-flow-hub.zip` em **Plugins → Adicionar novo → Enviar plugin** e ative.

> O plugin do cliente já aponta para `https://commentflow.aceleradordeclinicas.com.br`. Para usar outro endereço, defina no `wp-config.php` de cada cliente:
> `define( 'CFLOW_HUB_URL', 'https://seu-hub.com.br' );`

## Configurando o app central na Meta

Crie um **app novo** só para o hub. Não reaproveite o app de um site: cada app tem uma única URL de webhook.

1. Em developers.facebook.com, **Criar app** com o caso de uso **"Gerenciar mensagens e conteúdo no Instagram"** → **Configuração da API com login do Instagram**.
2. **Adicionar permissões obrigatórias:** `instagram_business_basic`, `instagram_business_manage_comments` e `instagram_business_manage_messages`.
3. Copie as URLs de **Comment Flow Hub → Configurações**:
   - **Configurar webhooks:** URL de callback + token de verificação. Assine os campos `comments` e `messages`.
   - **Configurar o login da empresa → Configurações:**
     - URI de redirecionamento do OAuth;
     - URL de retorno de chamada para cancelar autorização;
     - URL de solicitação de exclusão de dados.
4. Cole o **ID** e a **chave secreta do app do Instagram** no hub e salve.
5. Em **Configurações do app → Básico**, preencha a política de privacidade, os termos e a categoria, e **publique** o app.

## Atendendo clientes

1. **Comment Flow Hub → Licenças → Gerar chave** com o nome do cliente.
2. Envie a chave ao cliente.
3. O cliente:
   - instala o plugin **Comment Flow**;
   - abre **Configurações → Licença Comment Flow**;
   - cola a chave em **Ativar licença**;
   - clica em **Conectar com o Instagram**.

### Antes da aprovação da Meta

Enquanto o app não passa pela análise, ele só funciona com contas que têm **função no app**. Para cada cliente:
- adicione a conta do Instagram em **Funções do app → Testador do Instagram**;
- o cliente aceita o convite em **Instagram → Configurações → Apps e sites → Convites de testador**.

Isso é suficiente para começar com alguns clientes.

### Liberando para qualquer conta (análise do app)

1. **Verificação da empresa** no Gerenciador de Negócios da Meta.
2. Em **Análise do app**, peça **Acesso Avançado** para as três permissões. Para cada uma, explique o uso e envie um vídeo de tela mostrando o fluxo:
   - conectar a conta no plugin;
   - criar uma automação;
   - comentar e receber a resposta pública e a DM.
3. Depois da aprovação, qualquer conta profissional conecta sem convite de testador.

## Operação

- **Licenças:** "Desativar" (cliente inadimplente, por exemplo) corta os eventos na hora. "Liberar site" permite ativar a chave em outro endereço. "Excluir" remove tudo.
- **Entregas:** mostra os eventos repassados e as falhas. Muitas falhas para o mesmo cliente costumam indicar site fora do ar, plugin desativado ou um firewall bloqueando `/wp-json/`.
- Uma conta do Instagram fica ligada a uma licença só. Se ela for conectada em outro site, passa a receber os eventos lá.
- Se o hub ficar fora do ar, a Meta tenta reenviar os webhooks por algumas horas. Mesmo assim, mantenha o hub num servidor estável.

## Estrutura

```
comment-flow-hub/
├── comment-flow-hub.php
├── uninstall.php
├── includes/
│   ├── class-cfhub-licenses.php   # licenças, assinaturas, vínculo conta → licença
│   ├── class-cfhub-forwarder.php  # repasse dos webhooks + novas tentativas
│   ├── class-cfhub-rest.php       # rotas da Meta e dos sites
│   ├── class-cfhub-instagram.php  # OAuth, inscrição nos webhooks, signed_request
│   ├── class-cfhub-settings.php
│   ├── class-cfhub-db.php
│   └── class-cfhub-crypto.php
├── admin/                          # telas (licenças, entregas, configurações)
└── assets/admin.css                # gerado pelo Tailwind (npm run build:css na raiz)
```

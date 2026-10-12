# Comment Flow – plataforma de automações para Instagram

Um plugin WordPress que vira uma plataforma no estilo ManyChat:

- **cada pessoa se cadastra** (e-mail + senha) e **conecta o próprio Instagram** com um clique;
- **cada uma vê só os seus dados**: automações, leads, logs e a conta do Instagram;
- o painel fica no **front-end**, na página onde você colocar o shortcode **`[comment_flow]`** (ex.: a home do site);
- você, administrador, configura **um único app da Meta** para todo mundo no wp-admin.

Quando alguém comenta uma palavra-chave num post ou reel, o plugin

1. **responde publicamente** ao comentário (sorteando uma entre várias variações) e
2. **envia uma DM** (Private Reply) com texto e, se você quiser, um botão com link;
3. opcionalmente, **pede o e-mail e o telefone** e/ou **exige que a pessoa siga a conta** antes de entregar o material.

Ele usa somente a **API oficial da Meta** (Instagram API with Instagram Login). Não há scraping nem login com senha do Instagram.

## Requisitos

- WordPress 6.2+ e PHP 7.4+ (com `openssl`).
- Site em **HTTPS** e com **links permanentes amigáveis** (Configurações > Links permanentes ≠ "Simples").
- Contas do Instagram **Profissionais** (Empresa ou Criador de conteúdo).
- Um app no [Meta for Developers](https://developers.facebook.com/apps/).
- Recomendado: plugin **Action Scheduler** (já vem com o WooCommerce) e um cron real no servidor. Sem ele, o plugin usa o WP-Cron, que só roda quando o site recebe visitas.

## Instalação (ex.: commentflow.aceleradordeclinicas.com.br)

1. Crie o subdomínio com um WordPress novo e HTTPS.
2. Envie o `.zip` em **Plugins > Adicionar novo > Enviar plugin** e ative.
3. Ao ativar, o plugin cria a página "Comment Flow" com `[comment_flow]` e, se o site ainda mostra os posts na home, já a define como página inicial. Se o site já tinha outra página inicial, use o botão **Usar como página inicial** em *Comment Flow > Configurações*. Também funciona com Elementor e outros construtores: basta colocar o shortcode na página.
4. Em **wp-admin > Comment Flow > Configurações**, copie as 5 URLs para o app da Meta (próxima seção) e salve o **App ID** e o **App Secret** do Instagram.
5. Abra o site: aparece a tela **Entrar / Criar conta**. Depois do cadastro, a pessoa cai em **Minha conta** e clica em **Conectar com o Instagram**.

Na página do shortcode, o painel é exibido em **tela cheia**, sem o tema do site, para ficar igual em qualquer tema. Você pode desligar isso nas configurações.

### Usuários e acesso

- O cadastro libera o acesso na hora. Para fechar novos cadastros, desligue **Permitir novos cadastros**.
- Quem se cadastra recebe a função **Membro Comment Flow**: não entra no wp-admin e não vê a barra de administração.
- **wp-admin > Comment Flow > Usuários** lista os membros, com o Instagram conectado, o número de automações e o de leads. Para bloquear alguém, troque a função ou exclua o usuário.
- "Esqueci a senha" e "Alterar senha" usam o fluxo padrão do WordPress, então o site precisa enviar e-mails (de preferência com um plugin de SMTP).
- Uma conta do Instagram só pode estar ligada a um usuário por vez.

## Configurando o app na Meta

1. Em developers.facebook.com, crie um app do tipo **Empresa** e adicione o produto **Instagram**.
2. Abra **Instagram > Configuração da API com login do Instagram**:
   - Copie o **Instagram App ID** e o **Instagram App Secret**. Atenção: são diferentes dos IDs do app do Facebook.
   - Em **Configurar login empresarial do Instagram**, cadastre a **URL de redirecionamento OAuth**.
   - Em **Configurar webhooks**, informe a **URL de callback** e o **Token de verificação**. Depois assine os campos `comments` e `messages`.
   - Em **Configurações do login da empresa**, cadastre a **URL para cancelar autorização** e a **URL de exclusão de dados**.
   - Todas essas URLs aparecem, com botão de copiar, em *wp-admin > Comment Flow > Configurações*.
3. Um único webhook atende todo mundo: o plugin descobre de qual usuário é cada evento pela conta do Instagram que o recebeu.
4. Enquanto o app estiver em modo de desenvolvimento, só funcionam contas com função no app. Adicione cada uma em **Funções do app > Testadores do Instagram**; a pessoa aceita o convite no Instagram (Configurações > Apps e sites).

### Liberando para qualquer conta (App Review)

Para atender contas que não têm função no app, você precisa de:

- **Verificação da empresa** no Business Manager;
- **App Review** com **Acesso Avançado** para `instagram_business_basic`, `instagram_business_manage_comments` e `instagram_business_manage_messages`, incluindo vídeos mostrando o fluxo;
- uma **URL de política de privacidade** e as URLs de cancelamento e exclusão de dados (o plugin já responde nelas: ao receber o aviso da Meta, desconecta a conta e apaga o token);
- colocar o app em modo **Ao vivo**.

## Criando uma automação

No painel, **Nova automação**:

| Campo | O que faz |
|---|---|
| Post | "Qualquer post" ou um post/reel específico, escolhido pela miniatura ou pelo ID |
| Palavras-chave | Separadas por vírgula ou linha. Maiúsculas e acentos são ignorados. Se ficar vazio, responde a qualquer comentário |
| Modo | *Contém a palavra* (`quero` casa com "Eu quero!", mas não com "querosene"), *contém o trecho* ou *exatamente igual* |
| Resposta pública | Uma variação por linha. `{username}` vira `@usuario` |
| DM | Texto da Private Reply, com botão opcional (título de até 20 caracteres + URL) |
| Prioridade | Quando mais de uma automação casa, vale a de menor número |

## Gatilhos

Cada automação tem um tipo de gatilho, escolhido no topo do formulário:

| Gatilho | Quando dispara | Observações |
|---|---|---|
| **Comentário em post** | Alguém comenta uma palavra-chave num post ou reel | Responde no comentário e manda a Private Reply |
| **Palavra-chave na DM** | Alguém manda a palavra no direct (ex.: "OI") | Palavras-chave obrigatórias. Quem mandar de novo recebe de novo |
| **Boas-vindas (primeira DM)** | Alguém manda a **primeira** mensagem para a conta | Só uma por pessoa. Quem já conversava antes não recebe |

Se a mensagem casar com uma palavra-chave de DM, vale essa automação, mesmo que seja a primeira mensagem.

> **Sobre "novos seguidores":** a API oficial não avisa quando alguém segue a conta e não permite mandar DM para quem nunca interagiu. Para dar boas-vindas a novos seguidores, convide-os a puxar conversa ("me manda OI no direct" na bio ou o link `ig.me/m/seuusuario`) e use o gatilho de boas-vindas ou de palavra-chave.

Para identificar a primeira mensagem, o plugin guarda quem já conversou com a conta (tabela `cflow_contacts`). Para alguém que ele ainda não conhece, consulta o histórico da conversa na API: se houver mensagens com mais de 10 minutos, a pessoa já conversava antes e não recebe as boas-vindas.

## Seguir para liberar

Com **Exigir que siga a conta** ligado, antes de enviar a mensagem final o plugin confere se a pessoa segue a conta (campo `is_user_follow_business` da API):

```
Segue?  ── sim ──▶ envia a mensagem final
   └── não ──▶ "Me segue e toca em 'Já segui' 👇"  [Já segui ✅]
                  └─ tocou ─▶ confere de novo (repete até seguir)
```

A verificação vem depois da captura de e-mail e telefone. Em automações de comentário, ligar essa opção faz a primeira DM virar o convite com botão, porque a consulta só é permitida depois que a pessoa responde. Se a API não conseguir confirmar, o plugin entrega mesmo assim e registra o erro no lead.

## Captura de e-mail e telefone (leads)

Ligue **Pedir e-mail** e/ou **Pedir telefone** no passo 3 da automação. O fluxo fica assim:

```
Comentário "QUERO"
  → DM convite: "Quer receber o e-book? Toca no botão 👇"  [Sim, quero!]
  → pessoa responde (isso abre a janela de 24h para conversar)
  → "Qual é o seu melhor e-mail?"     → valida; até 3 tentativas
  → "E qual é o seu WhatsApp com DDD?" → valida; "PULAR" pula
  → mensagem final (passo 4) com o botão/link
```

- O e-mail é encontrado mesmo no meio de uma frase ("é ana@x.com, valeu"). O telefone é normalizado para `+55DDNÚMERO`.
- Depois de 3 respostas inválidas, o plugin pula aquele dado e segue com a entrega, para a conversa não travar.
- Quem já concluiu a captura e comenta de novo recebe a entrega direto, sem responder às perguntas outra vez.
- A data do consentimento fica registrada no lead. Deixe claro na pergunta para que o dado será usado (LGPD).

**Leads** mostra @usuário, e-mail, telefone (com link para o WhatsApp), automação, último comentário e etapa da captura, com filtro por automação e busca. O botão **Exportar CSV** respeita o filtro, e na lista de automações cada uma tem o seu link **Exportar CSV**. O arquivo usa `;` como separador e BOM UTF-8, então abre direto no Excel em português. Para atender um pedido de exclusão de dados, use **Excluir** no lead.

Ao contrário dos logs, os leads **não** são apagados automaticamente.

Em **Logs** aparecem cada comentário recebido, a automação aplicada, o que foi enviado e os erros da API.

## Como funciona

```
Comentário → webhook da Meta → /wp-json/comment-flow/v1/webhook
  → valida a assinatura X-Hub-Signature-256 (HMAC com o App Secret)
  → acha o usuário dono da conta do Instagram (entry.id)
  → grava no log desse usuário (chave única no comment_id = nunca responde duas vezes)
  → agenda na fila (Action Scheduler ou WP-Cron) e responde 200 na hora
Fila → escolhe a automação → resposta pública + Private Reply
  → erros temporários/limite de taxa: até 4 tentativas com backoff (2, 4, 8 min)
```

- Todas as tabelas têm `user_id`, e toda consulta filtra pelo usuário atual (o logado, o dono da conta no webhook ou o dono da tarefa na fila).
- O token de acesso de cada usuário (60 dias) fica **criptografado** (AES-256-GCM) e é **renovado automaticamente** por um cron diário. Para usar uma chave própria, defina `CFLOW_ENCRYPTION_KEY` no `wp-config.php` **antes** de configurar o app e conectar as contas.
- Comentários feitos pela própria conta são ignorados, e isso inclui as respostas do plugin. Assim não há loop.
- Logs com mais de 90 dias são apagados automaticamente.

## Limites da plataforma

- **Uma Private Reply por comentário**, enviada em até **7 dias** após o comentário.
- Mensagens livres só são permitidas na **janela de 24h** que se abre quando a pessoa responde a DM.
- A Meta aplica limites de chamadas por conta. Em posts virais, a fila e as novas tentativas absorvem os picos.
- A API não permite enviar DM para quem só curtiu o post ou para quem nunca interagiu com a conta.

## Estrutura

```
comment-flow/
├── comment-flow.php                  # bootstrap
├── uninstall.php                     # remove tabelas, opções e a função de membro
├── includes/
│   ├── class-cflow-db.php            # tabelas (todas com user_id) e migração
│   ├── class-cflow-tenant.php        # usuário dono dos dados da requisição/tarefa
│   ├── class-cflow-accounts.php      # conta do Instagram de cada usuário
│   ├── class-cflow-settings.php      # app da Meta e opções da plataforma
│   ├── class-cflow-crypto.php        # AES-256-GCM
│   ├── class-cflow-instagram-api.php # cliente da Graph API
│   ├── class-cflow-oauth.php         # conexão da conta, cancelamento e exclusão de dados
│   ├── class-cflow-webhook.php       # endpoint de webhook
│   ├── class-cflow-matcher.php       # regras de palavra-chave
│   ├── class-cflow-queue.php         # Action Scheduler / WP-Cron
│   ├── class-cflow-processor.php     # executa a automação
│   ├── class-cflow-conversation.php  # captura de e-mail/telefone na DM
│   ├── class-cflow-leads.php         # leads, validação e CSV
│   ├── class-cflow-contacts.php      # quem já conversou (primeira DM)
│   └── class-cflow-token-refresher.php
├── app/                              # painel do usuário (front-end)
│   ├── class-cflow-app.php           # rotas (?cf=...), ações e classes Tailwind reutilizadas
│   ├── class-cflow-auth.php          # cadastro, login e função de membro
│   ├── class-cflow-frontend.php      # shortcode [comment_flow] e tela cheia
│   └── views/                        # telas (partials/header.php = cabeçalho comum)
├── templates/app.php                 # página em tela cheia, sem o tema
├── admin/                            # wp-admin (só administradores)
│   ├── class-cflow-admin.php         # configurações da plataforma e usuários
│   └── views/
└── assets/
    ├── src/admin.css                 # fonte do CSS (Tailwind)
    └── admin.css                     # CSS compilado, carregado pelo WordPress
```

## Layout (tema escuro com Tailwind CSS)

As telas usam um tema escuro em preto, azul e branco, feito com **Tailwind CSS 3**. O CSS já vem compilado em `assets/admin.css`, então **instalar o plugin não exige Node nem build**.

Para não interferir no tema nem no resto do wp-admin:
- as utilities são geradas com o escopo `.cflow-app` (`important: '.cflow-app'` no `tailwind.config.js`) e só valem dentro das telas do plugin;
- o *preflight* (reset) do Tailwind fica desligado; um reset mínimo e escopado está em `assets/src/admin.css`;
- o fundo preto do wp-admin só é aplicado nas telas do plugin, pela classe `cflow-dark` no `<body>`.

Para alterar o visual, edite as views ou `assets/src/admin.css` e gere o CSS de novo na raiz do repositório:

```bash
npm install
npm run build:css   # ou: npm run watch:css
```

As classes mais usadas (cartão, input, botões, interruptor, tabela) ficam como constantes em `CFlow_App` (`CARD`, `INPUT`, `BTN_PRIMARY`…). Mudar uma delas muda todas as telas.

## Próximos passos sugeridos

- Integração direta com CRM/e-mail marketing (RD Station, Mailchimp, ActiveCampaign) ou webhook para Zapier/Make/n8n
- Planos e cobrança (ex.: limitar automações por plano)
- Várias contas do Instagram por usuário

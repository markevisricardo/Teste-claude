# Comment Flow – automação de comentários do Instagram para WordPress

Plugin no estilo ManyChat: quando alguém comenta uma palavra-chave num post ou reel, o plugin

1. **responde publicamente** ao comentário (sorteando uma entre várias variações) e
2. **envia uma DM** (Private Reply) com texto e, se você quiser, um botão com link;
3. opcionalmente, **pede o e-mail e o telefone** na DM antes de entregar o material. Os leads ficam no painel e podem ser exportados em CSV.

Ele usa somente a **API oficial da Meta** (Instagram API with Instagram Login). Não há scraping nem login com senha.

## Requisitos

- WordPress 6.2+ e PHP 7.4+ (com `openssl`).
- Site em **HTTPS** e com **links permanentes amigáveis** (Configurações > Links permanentes ≠ "Simples").
- Conta do Instagram **Profissional** (Empresa ou Criador de conteúdo).
- Um app no [Meta for Developers](https://developers.facebook.com/apps/).
- Recomendado: plugin **Action Scheduler** (já vem com o WooCommerce) e um cron real no servidor. Sem ele, o plugin usa o WP-Cron, que só roda quando o site recebe visitas.

## Instalação

1. Copie a pasta `comment-flow/` para `wp-content/plugins/`, ou compacte-a em `.zip` e envie em Plugins > Adicionar novo.
2. Ative o plugin. O menu **Comment Flow** vai aparecer no painel.

## Modos de conexão

Em **Comment Flow → Configurações** você escolhe o modo:

- **Licença Comment Flow** (para clientes): cole a chave recebida, clique em **Ativar licença** e depois em **Conectar com o Instagram**. Não precisa criar nada na Meta: o login e os webhooks passam pelo [Comment Flow Hub](../comment-flow-hub/README.md).
- **App próprio da Meta:** você cria e configura o seu app, como descrito abaixo. As instalações antigas continuam neste modo.

Trocar de modo desconecta a conta do Instagram, porque o token pertence ao app do modo anterior. As automações e os leads continuam.

## Configurando o app na Meta (modo app próprio)

1. Em developers.facebook.com, crie um app do tipo **Empresa** e adicione o produto **Instagram**.
2. Abra **Instagram > Configuração da API com login do Instagram**:
   - Copie o **Instagram App ID** e o **Instagram App Secret**. Atenção: são diferentes dos IDs do app do Facebook.
   - Em **Configurar login empresarial do Instagram**, cadastre a **URL de redirecionamento OAuth** exibida em *Comment Flow > Configurações*.
   - Em **Configurar webhooks**, informe a **URL de callback** e o **Token de verificação** que aparecem na mesma tela. Depois assine os campos `comments` e `messages`.
3. No WordPress, em *Comment Flow > Configurações*, cole o App ID e o App Secret, salve e clique em **Conectar com o Instagram**.
4. Enquanto o app estiver em modo de desenvolvimento, só funcionam contas com função no app. Adicione a sua em **Funções do app > Testadores do Instagram** e aceite o convite no Instagram (Configurações > Apps e sites).

### Usando em contas de clientes / publicando o app

Para atender contas que não têm função no app, você precisa de:

- **Verificação da empresa** no Business Manager;
- **App Review** com **Acesso Avançado** para `instagram_business_basic`, `instagram_business_manage_comments` e `instagram_business_manage_messages`, incluindo vídeos mostrando o fluxo;
- uma **URL de política de privacidade** e as instruções de exclusão de dados no app;
- colocar o app em modo **Ao vivo**.

## Criando uma automação

*Comment Flow > Nova automação*:

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

**Comment Flow > Leads** mostra @usuário, e-mail, telefone (com link para o WhatsApp), automação, último comentário e etapa da captura, com filtro por automação e busca. O botão **Exportar CSV** respeita o filtro, e na lista de automações cada uma tem o seu link **Exportar CSV**. O arquivo usa `;` como separador e BOM UTF-8, então abre direto no Excel em português. Para atender um pedido de exclusão de dados, use **Excluir** no lead.

Ao contrário dos logs, os leads **não** são apagados automaticamente.

Em *Comment Flow > Logs* aparecem cada comentário recebido, a automação aplicada, o que foi enviado e os erros da API.

## Como funciona

```
Comentário → webhook da Meta → /wp-json/comment-flow/v1/webhook
  → valida a assinatura X-Hub-Signature-256 (HMAC com o App Secret)
  → grava no log (chave única no comment_id = nunca responde duas vezes)
  → agenda na fila (Action Scheduler ou WP-Cron) e responde 200 na hora
Fila → escolhe a automação → resposta pública + Private Reply
  → erros temporários/limite de taxa: até 4 tentativas com backoff (2, 4, 8 min)
```

- O token de acesso (60 dias) fica **criptografado** (AES-256-GCM) e é **renovado automaticamente** por um cron diário. Para usar uma chave própria, defina `CFLOW_ENCRYPTION_KEY` no `wp-config.php` **antes** de conectar a conta.
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
├── uninstall.php                     # remove tabelas e opções
├── includes/
│   ├── class-cflow-db.php            # tabelas de automações, logs e leads
│   ├── class-cflow-settings.php      # opções (segredos criptografados)
│   ├── class-cflow-crypto.php        # AES-256-GCM
│   ├── class-cflow-instagram-api.php # cliente da Graph API
│   ├── class-cflow-oauth.php         # conexão da conta
│   ├── class-cflow-webhook.php       # endpoint de webhook
│   ├── class-cflow-matcher.php       # regras de palavra-chave
│   ├── class-cflow-queue.php         # Action Scheduler / WP-Cron
│   ├── class-cflow-processor.php     # executa a automação
│   ├── class-cflow-conversation.php  # captura de e-mail/telefone na DM
│   ├── class-cflow-leads.php         # leads, validação e CSV
│   ├── class-cflow-contacts.php      # quem já conversou (primeira DM)
│   └── class-cflow-token-refresher.php
├── admin/
│   ├── class-cflow-admin.php         # menus, handlers e classes Tailwind reutilizadas
│   └── views/                        # telas (partials/header.php = cabeçalho comum)
└── assets/
    ├── src/admin.css                 # fonte do CSS (Tailwind)
    └── admin.css                     # CSS compilado, carregado pelo WordPress
```

## Layout (tema escuro com Tailwind CSS)

O painel usa um tema escuro em preto, azul e branco, feito com **Tailwind CSS 3**. O CSS já vem compilado em `assets/admin.css`, então **instalar o plugin não exige Node nem build**.

Para não interferir no resto do wp-admin:
- as utilities são geradas com o escopo `.cflow-app` (`important: '.cflow-app'` no `tailwind.config.js`) e só valem dentro das telas do plugin;
- o *preflight* (reset) do Tailwind fica desligado; um reset mínimo e escopado está em `assets/src/admin.css`;
- o fundo preto do wp-admin só é aplicado nas telas do plugin, pela classe `cflow-dark` no `<body>`.

Para alterar o visual, edite as views ou `assets/src/admin.css` e gere o CSS de novo na raiz do repositório:

```bash
npm install
npm run build:css   # ou: npm run watch:css
```

As classes mais usadas (cartão, input, botões, interruptor, tabela) ficam como constantes em `CFlow_Admin` (`CARD`, `INPUT`, `BTN_PRIMARY`…). Mudar uma delas muda todas as telas.

## Próximos passos sugeridos

- Integração direta com CRM/e-mail marketing (RD Station, Mailchimp, ActiveCampaign) ou webhook para Zapier/Make/n8n
- Exigir que a pessoa siga o perfil antes de liberar o link
- Várias contas do Instagram por site

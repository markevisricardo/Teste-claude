# Comment Flow – automação de comentários do Instagram para WordPress

Plugin no estilo ManyChat: quando alguém comenta uma palavra-chave num post ou reel, o plugin

1. **responde publicamente** ao comentário (sorteando uma entre várias variações) e
2. **envia uma DM** (Private Reply) com texto e, se você quiser, um botão com link.

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

## Configurando o app na Meta

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
│   ├── class-cflow-db.php            # tabelas de automações e logs
│   ├── class-cflow-settings.php      # opções (segredos criptografados)
│   ├── class-cflow-crypto.php        # AES-256-GCM
│   ├── class-cflow-instagram-api.php # cliente da Graph API
│   ├── class-cflow-oauth.php         # conexão da conta
│   ├── class-cflow-webhook.php       # endpoint de webhook
│   ├── class-cflow-matcher.php       # regras de palavra-chave
│   ├── class-cflow-queue.php         # Action Scheduler / WP-Cron
│   ├── class-cflow-processor.php     # executa a automação
│   └── class-cflow-token-refresher.php
├── admin/                            # telas do painel
└── assets/admin.css
```

## Próximos passos sugeridos

- Fluxos de conversa com botões (quick replies), tratando o webhook `messages`
- Captura de e-mail na DM e integração com CRM/e-mail marketing
- Exigir que a pessoa siga o perfil antes de liberar o link
- Várias contas do Instagram por site

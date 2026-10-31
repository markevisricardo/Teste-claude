# Post Flow – agendamento de posts em redes sociais (SaaS em WordPress)

> Facebook, Instagram, LinkedIn, X, Threads, Google Meu Negócio, Pinterest e WhatsApp (Status e grupos por QR Code, grátis; ou API oficial).

Um plugin WordPress que vira uma plataforma de agendamento de posts, no estilo das ferramentas de "fila de publicação":

- **cada pessoa se cadastra** (e-mail + senha) e **conecta os próprios canais**;
- **cada uma vê só os seus dados**: canais, fila, calendário e histórico;
- o painel fica no **front-end**, na página com o shortcode **`[post_flow]`** (ex.: a home de um subdomínio);
- você, administrador, cadastra **um app por rede** no wp-admin, e ele serve a todos os usuários.

Redes suportadas, todas pelas **APIs oficiais** (sem senha nem scraping):

| Rede | O que conecta | Texto | Imagens |
|---|---|---|---|
| Facebook | Páginas | até 63.206 caracteres; link vira prévia | 1 a 10 (álbum) |
| Instagram | Perfis profissionais ligados a uma página | até 2.200; **exige imagem** | 1 a 10 (carrossel) |
| LinkedIn | Perfil pessoal | até 3.000 | 1 a 9 |
| X (Twitter) | Perfil | até 280 | 1 a 4 |
| Threads | Perfil | até 500 | 1 a 10 (carrossel) |
| Google Meu Negócio | Perfis da empresa (cada local é um canal) | até 1.500; **exige texto**; link vira botão "Saiba mais" | 0 ou 1 |
| Pinterest | Pastas (cada pasta é um canal) | até 500; 1ª linha vira o título; link vira o destino do pin; **exige imagem** | 1 a 5 |
| WhatsApp (QR Code) — **gratuito, não oficial** | Seu **Status** e os **grupos** do número (cada um é um canal) | até 4.096 | 0 ou 1 |
| WhatsApp (API oficial) — pago por mensagem | Seu número da WhatsApp Cloud API + lista de contatos | até 1.000; **exige texto**; entra no modelo aprovado | 0 ou 1 (se o modelo tiver imagem) |

### Quanto custa cada rede

- **Gratuitas** (só exigem criar o app e, para liberar a todos, a aprovação da rede): Facebook, Instagram, LinkedIn, Threads, Google Meu Negócio e Pinterest.
- **X**: tem plano gratuito com limite baixo de posts por mês; acima disso, é pago.
- **WhatsApp**: a API oficial cobra por mensagem. A alternativa **gratuita** é o **WhatsApp por QR Code**, via Evolution API instalada num servidor seu. É uma conexão não oficial (veja abaixo).

## O que o usuário faz

1. **Cria a conta** e cai na tela **Canais**.
2. **Conecta as redes**: é levado ao site de cada rede para autorizar. Se a conexão trouxer várias contas (ex.: várias páginas do Facebook), escolhe quais quer usar.
3. **Define os horários** da fila (dias da semana + horários + fuso).
4. **Cria posts**: texto, link e imagens, escolhe os canais e:
   - **Adicionar à fila**: sai no próximo horário livre;
   - **Escolher data e hora**;
   - **Publicar agora**;
   - ou **Salvar rascunho**.
5. Acompanha em **Fila**, **Calendário** (semana) e **Publicados** (com o link de cada post e o motivo das falhas, com botão "Tentar de novo").

O editor mostra o contador de caracteres de cada rede marcada e avisa antes de salvar (ex.: "Instagram exige ao menos uma imagem"). Se faltar algo, o post é guardado como rascunho, e nada se perde.

## Requisitos

- WordPress 6.2+ e PHP 7.4+ (com `openssl` e GD ou Imagick).
- Site em **HTTPS** e com **links permanentes** diferentes de "Simples".
- **Cron funcionando**: o publicador roda a cada minuto pelo WP-Cron. Como o WP-Cron só dispara com visitas, crie um cron no servidor:

  ```
  */1 * * * * curl -s https://SEU-SITE/wp-cron.php?doing_wp_cron > /dev/null
  ```
- Envio de e-mails (plugin SMTP) para "Esqueci a senha".

## Instalação

1. Crie o site (ex.: `app.suaempresa.com.br`) com HTTPS.
2. Envie o `.zip` da pasta `post-flow/` em **Plugins > Adicionar novo > Enviar plugin** e ative.
3. Ao ativar, o plugin cria a página "Post Flow" com `[post_flow]` e, se o site ainda mostra os posts na home, a define como página inicial. Se não, use o botão **Usar como página inicial** em *Post Flow > Configurações*.
4. Em **wp-admin > Post Flow > Configurações**, copie a **URL de retorno** de cada rede para o app daquela rede e cole as credenciais (próxima seção).
5. Abra o site numa janela anônima: aparece **Entrar / Criar conta**.

## Configurando os apps das redes

Cada rede tem a sua URL de retorno, mostrada com botão de copiar no wp-admin (formato `https://SEU-SITE/wp-json/post-flow/v1/oauth/{rede}`).

### Meta (Facebook + Instagram)

1. Em [developers.facebook.com/apps](https://developers.facebook.com/apps/), crie um app do tipo **Empresa** e adicione **Login do Facebook para Empresas**.
2. Em *Configurações*, cadastre a URL de retorno em **URIs de redirecionamento do OAuth válidos**.
3. Em *Configurações do app > Básico*, preencha a política de privacidade e as URLs de **cancelamento de autorização** e **exclusão de dados** (também estão no wp-admin).
4. Permissões usadas: `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `business_management`, `instagram_basic`, `instagram_content_publish`.
5. Até o app ser aprovado, só funcionam contas com função no app (administradores, desenvolvedores e testadores). Para liberar para todo mundo: **verificação da empresa** + **App Review** com **Acesso Avançado** dessas permissões (com vídeo do fluxo) + modo **Ao vivo**.

### LinkedIn

1. Em [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps), crie o app (precisa de uma página da empresa).
2. Em *Products*, adicione **Sign In with LinkedIn using OpenID Connect** e **Share on LinkedIn**.
3. Em *Auth*, cadastre a URL de retorno e copie o Client ID e o Client Secret.
4. O token vale 60 dias. Sem o programa de parceiros do LinkedIn não há renovação automática: o painel avisa quando o canal precisa ser reconectado.
5. A versão da API está em `PFLOW_LINKEDIN_VERSION` (padrão `202606`). O LinkedIn aposenta versões depois de cerca de um ano; atualize definindo a constante no `wp-config.php`.

### X (Twitter)

1. Em [developer.x.com](https://developer.x.com/en/portal/dashboard), crie o projeto e o app.
2. Em *User authentication settings*: permissões **Read and write**, tipo **Web App (confidential client)** e a URL de retorno em **Callback URI**.
3. Copie o **OAuth 2.0 Client ID** e o **Client Secret**.
4. Publicar exige um plano da API do X que permita escrita (o gratuito tem um limite mensal baixo de posts).

### Threads

1. Em [developers.facebook.com/apps](https://developers.facebook.com/apps/), crie um app com o caso de uso **Acessar a API do Threads**.
2. Cadastre a URL de retorno em **URLs de callback de redirecionamento** e use o **ID e a chave secreta do Threads** (diferentes dos do app).
3. Permissões: `threads_basic` e `threads_content_publish`. O token de 60 dias é renovado sozinho.

### Google Meu Negócio (Google Business Profile)

1. No [Google Cloud Console](https://console.cloud.google.com/), crie um projeto e ative as APIs **My Business Account Management**, **My Business Business Information** e **Google My Business**.
2. Configure a tela de consentimento OAuth e crie um **ID do cliente OAuth** do tipo **Aplicativo da Web**, com a URL de retorno em "URIs de redirecionamento autorizados".
3. **Peça o acesso à Business Profile API** no formulário do Google. Sem a aprovação, as APIs têm cota zero e a conexão falha. A análise costuma levar alguns dias.
4. Escopo: `https://www.googleapis.com/auth/business.manage`. O plugin pede acesso "offline" e renova o token sozinho.
5. Cada local (perfil da empresa) vira um canal. O post sai como "Novidade", com foto opcional e o botão **Saiba mais** apontando para o link.

### Pinterest

1. Em [developers.pinterest.com/apps](https://developers.pinterest.com/apps/), crie o app (exige conta empresarial do Pinterest).
2. Cadastre a URL de retorno em **Redirect URIs** e copie o **App ID** e a **App secret key**.
3. Escopos: `boards:read`, `pins:read`, `pins:write` e `user_accounts:read`.
4. No acesso **Trial**, os pins criados ficam visíveis só para o dono da conta. Peça o acesso **Standard** (com vídeo do fluxo) para publicar de verdade.
5. Cada pasta vira um canal. O token de 30 dias é renovado sozinho.

### WhatsApp por QR Code (gratuito, Evolution API)

Sem custo por mensagem e sem aprovação da Meta: cada usuário conecta o próprio número **lendo um QR Code** no painel, como no WhatsApp Web, e publica no **Status** e nos **grupos** em que o número está.

> ⚠️ **Conexão não oficial.** Ela vai contra os termos de uso do WhatsApp, e o número pode ser **bloqueado**, principalmente se publicar muito, entrar em muitos grupos ou receber denúncias. Use um **número dedicado** (não o principal da empresa) e publique só onde as pessoas querem receber. O painel mostra esse aviso para o usuário.

Como configurar (administrador, uma vez só):

1. Instale a [Evolution API](https://doc.evolution-api.com/) (código aberto) num servidor seu com Docker, seguindo a documentação dela, que inclui o banco de dados. Use HTTPS (ex.: `https://whatsapp.suaempresa.com.br`).
2. Defina a chave global `AUTHENTICATION_API_KEY` no servidor.
3. Em **wp-admin > Post Flow > Configurações**, informe a **URL do servidor** e a **chave global**.

Como o usuário usa:

1. Em **Canais > WhatsApp (QR Code)**, clica em **Gerar QR Code**. O plugin cria uma instância só dele no servidor.
2. Lê o QR Code no celular (Configurações → Dispositivos conectados → Conectar dispositivo). A tela detecta a conexão sozinha e troca o QR quando ele expira.
3. Escolhe os destinos: **Meu Status** e os grupos. Cada um vira um canal no editor.
4. Se o celular desconectar, o post falha com o aviso "WhatsApp desconectado" e o canal pede um novo QR Code. **Desconectar número** apaga a instância do servidor. Excluir a conta também apaga.

Detalhes: no Status, um post só de texto sai com fundo roxo; com imagem, a imagem vai com o texto como legenda. Nos grupos, sai como mensagem (ou imagem com legenda). O link vai no fim do texto. Canais do WhatsApp (newsletters) e listas de contatos individuais não são suportados nesse modo.

### WhatsApp (API oficial, paga)

O WhatsApp **não tem API oficial para postar em Status nem em Canais**. O caminho oficial é a **WhatsApp Cloud API**: o post é enviado como mensagem para uma lista de contatos que **autorizaram receber** (opt-in), usando um **modelo de mensagem aprovado pela Meta**, porque fora da janela de 24 horas de conversa só modelos são permitidos.

Não há nada para configurar no wp-admin: cada usuário conecta o próprio número em **Canais > Conectar WhatsApp Business**, informando:

- o **ID do número de telefone** e um **token de acesso permanente** (de um usuário do sistema no Gerenciador de Negócios, com `whatsapp_business_messaging`);
- o **nome e o idioma do modelo**, que deve ser de categoria **Marketing** e ter a variável `{{1}}` no meio do texto. A Meta recusa modelos que começam ou terminam com variável. Exemplo: `Novidade da Clínica Sorriso: {{1}} — Responda SAIR para não receber mais.`;
- se o modelo tem **imagem no cabeçalho**. Nesse caso, vai a 1ª imagem do post;
- a **lista de contatos** (com DDI, até 250 por canal).

O texto do post entra no lugar de `{{1}}`. As quebras de linha viram " · ", porque variáveis de modelo não aceitam quebra de linha. Cada mensagem de marketing é **cobrada pela Meta** por conversa. Envie só para quem pediu: números denunciados como spam perdem qualidade e podem ser bloqueados.

Redes sem credenciais aparecem para os usuários como "Indisponível no momento", então dá para lançar com só algumas.

## Multiusuário (SaaS)

- Quem se cadastra recebe a função **Membro Post Flow**: não entra no wp-admin e não vê a barra do WordPress.
- Todas as tabelas têm `user_id`, e toda leitura filtra pelo usuário atual. Os testes cobrem o isolamento (um usuário não lê, não edita e não exclui dados de outro).
- **Limite de canais por usuário** (em *Configurações*) para montar planos. Cobrança não está incluída. Para cobrar, dá para integrar com WooCommerce Subscriptions, Stripe etc. e ajustar o limite por plano.
- **wp-admin > Post Flow > Usuários** lista cada pessoa com os canais por rede e os posts agendados, publicados e com falha.
- O usuário pode **excluir a conta** no painel, o que apaga canais, tokens, posts e imagens (atende à LGPD). Excluir pelo wp-admin faz o mesmo.

## Como funciona por dentro

| Parte | Arquivo |
|---|---|
| Tabelas (`pflow_channels`, `pflow_posts`, `pflow_targets`) | `includes/class-pflow-db.php` |
| Uma integração por rede (OAuth, publicação, renovação de token) | `includes/providers/` |
| Conexão OAuth (state de uso único, PKCE no X, escolha de contas) | `includes/class-pflow-oauth.php` |
| Fila: próximo horário livre, fuso do usuário | `includes/class-pflow-schedule.php` |
| Publicador (WP-Cron a cada minuto, novas tentativas) | `includes/class-pflow-publisher.php` |
| Imagens (convertidas para JPG, que todas as redes aceitam) | `includes/class-pflow-media.php` |
| Painel (telas e ações) | `app/` |
| wp-admin | `admin/` |

- Cada post gera um **destino por canal**. O publicador reserva cada destino com um `UPDATE ... WHERE status = 'pending'` atômico, então dois processos nunca publicam o mesmo post.
- **Falhas temporárias** (rede fora, 429, 5xx) tentam de novo em 5 e 20 minutos. As **definitivas** (texto recusado, token inválido) ficam como falha, com a mensagem da rede.
- Um destino preso em "publicando" por mais de 15 minutos (processo interrompido) vira falha, sem nova tentativa automática, para não arriscar post duplicado.
- Tokens ficam **criptografados** (AES-256-GCM). Defina `PFLOW_ENCRYPTION_KEY` no `wp-config.php` para usar uma chave própria. Trocar a chave exige reconectar os canais.

## Desenvolvimento

```bash
npm install
npm run build:postflow   # gera post-flow/assets/app.css (Tailwind)
npm run test:postflow    # testes de lógica + integração (PHP, sem WordPress)
npm run preview:postflow # gera as telas em HTML com dados de exemplo (docs/preview-post-flow)
```

Os testes rodam com stubs do WordPress e um banco SQLite em memória. As APIs das redes são simuladas. Eles cobrem a fila, as regras de cada rede, a publicação em todas as redes, as novas tentativas, os tokens vencidos, o isolamento entre usuários e a exclusão da conta.

## Limitações conhecidas / próximos passos

- **Vídeo** (Reels, vídeos no X/LinkedIn/Threads/Pinterest) ainda não é suportado, só texto e imagens.
- WhatsApp por QR Code: depende do servidor Evolution API estar no ar, e o número pode ser bloqueado pelo WhatsApp (conexão não oficial).
- WhatsApp (API oficial): a lista de contatos é digitada no canal (até 250). Não há importação de CSV, descadastro automático por "SAIR" (precisaria do webhook da Cloud API) nem Embedded Signup, que conectaria o número em um clique sem copiar o token.
- Google Meu Negócio: só posts do tipo "Novidade" (sem Evento nem Oferta).
- Instagram: só perfis profissionais ligados a uma página do Facebook (via Login do Facebook).
- LinkedIn: só perfil pessoal. Páginas de empresa exigem o programa Community Management API.
- Não há texto diferente por rede no mesmo post (dá para usar "Duplicar" e ajustar).
- Não há métricas (curtidas, alcance) nem equipe/vários usuários na mesma conta.
- A publicação nunca foi testada contra as APIs reais neste repositório (só com respostas simuladas). Faça o primeiro teste com contas de desenvolvedor de cada rede.

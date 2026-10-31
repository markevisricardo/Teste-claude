# Post Flow – agendamento de posts em redes sociais (SaaS em WordPress)

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

- **Vídeo** (Reels, vídeos no X/LinkedIn/Threads) ainda não é suportado, só texto e imagens.
- Instagram: só perfis profissionais ligados a uma página do Facebook (via Login do Facebook).
- LinkedIn: só perfil pessoal. Páginas de empresa exigem o programa Community Management API.
- Não há texto diferente por rede no mesmo post (dá para usar "Duplicar" e ajustar).
- Não há métricas (curtidas, alcance) nem equipe/vários usuários na mesma conta.
- A publicação nunca foi testada contra as APIs reais neste repositório (só com respostas simuladas). Faça o primeiro teste com contas de desenvolvedor de cada rede.

# Comment Flow

Plataforma de automações para Instagram no estilo ManyChat, feita como **um único plugin WordPress** e usando só a API oficial da Meta.

Cada pessoa se cadastra com e-mail e senha, conecta o próprio Instagram em um clique e vê **apenas os seus dados**: automações, leads e logs. O painel fica no front-end do site, na página onde você colocar o shortcode `[comment_flow]` (por exemplo, a home de `commentflow.aceleradordeclinicas.com.br`).

O que o plugin faz:

- responde comentários por palavra-chave e manda DM (Private Reply);
- responde palavras-chave e a primeira mensagem no direct;
- só libera o link para quem segue a conta ("seguir para liberar");
- captura e-mail e telefone;
- exporta os leads em CSV.

| Pasta | O que é |
|---|---|
| [`comment-flow/`](comment-flow/) | O plugin ([README](comment-flow/README.md)) |
| [`post-flow/`](post-flow/) | **Post Flow**: plataforma multiusuário para agendar posts no Facebook, Instagram, LinkedIn, X, Threads, Google Meu Negócio, Pinterest e WhatsApp ([README](post-flow/README.md)) |

## Post Flow

Cada pessoa se cadastra, conecta os próprios canais e agenda posts por fila de horários, por data ou para agora. O painel fica no shortcode `[post_flow]`.

| Fila | Criar post |
|---|---|
| ![Fila](docs/screenshots/post-flow/queue.png) | ![Criar post](docs/screenshots/post-flow/compose.png) |

| Calendário | Canais |
|---|---|
| ![Calendário](docs/screenshots/post-flow/calendar.png) | ![Canais](docs/screenshots/post-flow/channels.png) |

| Publicados | Entrar / criar conta |
|---|---|
| ![Publicados](docs/screenshots/post-flow/sent.png) | ![Entrar](docs/screenshots/post-flow/auth.png) |

## Telas

| Entrar / criar conta | Automações |
|---|---|
| ![Entrar](docs/screenshots/login.png) | ![Automações](docs/screenshots/dashboard.png) |

| Minha conta (conectar o Instagram) | Leads |
|---|---|
| ![Minha conta](docs/screenshots/account-new.png) | ![Leads](docs/screenshots/leads.png) |

| Editor de automação | Logs |
|---|---|
| ![Editor](docs/screenshots/edit.png) | ![Logs](docs/screenshots/logs.png) |

| wp-admin: configurações da plataforma | wp-admin: usuários |
|---|---|
| ![Configurações](docs/screenshots/admin-settings.png) | ![Usuários](docs/screenshots/admin-users.png) |

## Desenvolvimento

O CSS é feito com Tailwind CSS e já vem compilado em `comment-flow/assets/admin.css`. Para gerar de novo depois de mudar as telas:

```bash
npm install
npm run build:css
```

Post Flow: `npm run build:postflow` (CSS) e `npm run test:postflow` (testes).

Para instalar no WordPress, envie só a pasta do plugin (`comment-flow/` ou `post-flow/`), ou um `.zip` dela. `node_modules`, `package.json` e `docs/` não vão para o site.

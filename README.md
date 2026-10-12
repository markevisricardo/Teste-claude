# Comment Flow

Plugin WordPress de automação de comentários do Instagram, no estilo ManyChat. Ele responde comentários por palavra-chave, envia DM (Private Reply), captura e-mail e telefone na conversa e exporta os leads em CSV, tudo pela API oficial da Meta.

O plugin está em [`comment-flow/`](comment-flow/). A instalação, a configuração do app na Meta e os detalhes estão no [README do plugin](comment-flow/README.md).

## Telas

| Automações | Leads |
|---|---|
| ![Automações](docs/screenshots/automations.png) | ![Leads](docs/screenshots/leads.png) |

| Editar automação | Configurações |
|---|---|
| ![Editar automação](docs/screenshots/edit.png) | ![Configurações](docs/screenshots/settings.png) |

## Desenvolvimento

O CSS do painel é feito com Tailwind CSS e já vem compilado em `comment-flow/assets/admin.css`. Para gerar de novo depois de mudar as telas:

```bash
npm install
npm run build:css
```

Para instalar no WordPress, envie só a pasta `comment-flow/` (ou um `.zip` dela). `node_modules`, `package.json` e `docs/` não precisam ir para o site.

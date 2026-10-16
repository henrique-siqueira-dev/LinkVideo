# linkVideo — Repositório de vídeos (Laravel 12)

Dashboard onde usuários **master** cadastram vídeos (título, capa, descrição, tipo e classificação indicativa) e qualquer usuário logado assiste num player em modal.

| Tela | O que faz |
|---|---|
| **Biblioteca** (`/dashboard`) | Grade de capas com selo de classificação, busca por título, filtros por tipo e classificação. Botão **▶ Assistir** abre o player em modal |
| **Gerenciar vídeos** (`/videos`, só master) | Lista com assistir, editar e excluir (excluir apaga também o arquivo e a capa) |
| **Novo / editar vídeo** | Formulário com pré-visualização da capa e upload do vídeo com barra de progresso (arrastar e soltar) |

### Regras principais

- **Papéis**: `master` (gerencia vídeos) e `user` (só assiste). Os dois masters vêm do seeder / SQL de instalação (`config/linkvideo.php`).
- **Upload em partes**: o navegador envia o vídeo em pedaços de 8 MB (`VIDEO_CHUNK_MB`), que o servidor junta em `storage/app/private/uploads-tmp`. Assim o vídeo não fica limitado ao `upload_max_filesize` da hospedagem. Se o PHP aceitar menos que 8 MB, a parte diminui sozinha. Limite do vídeo: `VIDEO_MAX_MB` (4 GB).
- **Vídeos protegidos**: vídeos e capas ficam em `storage/app/private` (fora da internet) e são entregues por rota com login. O player consegue avançar/voltar (respostas `206 Partial Content`).
- O conteúdo é conferido pelo tipo real do arquivo (não só pela extensão). Formato recomendado: **MP4 (H.264)**. `.mov` do iPhone em HEVC pode não tocar no Chrome.
- **Classificação indicativa** brasileira: Livre, 10, 12, 14, 16, 18 (`app/Enums/AgeRating.php`). **Tipos** em `app/Enums/Genre.php`.

## Como rodar (XAMPP no macOS)

```bash
/Applications/XAMPP/xamppfiles/bin/php artisan serve --port=8010
```

Acesse **http://127.0.0.1:8010**. Banco MySQL do XAMPP `linkvideo` (`utf8mb4_unicode_ci`). Para recriar do zero:

```bash
/Applications/XAMPP/xamppfiles/bin/php artisan migrate:fresh --seed
```

O seeder cria os masters com a senha de `MASTER_PASSWORD` do `.env`.

> O Apache do XAMPP roda como `daemon` e não consegue gravar em `storage/`; por isso o `artisan serve`.

## Publicar com Docker (Coolify)

Arquivos: `Dockerfile` (nginx + PHP-FPM 8.4, imagem `serversideup/php`), `docker-compose.yml` (app + MySQL 8.4) e `docker/nginx/linkvideo-media.conf`.

1. No Coolify: **+ New Resource → Public/Private Repository**, aponte para este repositório e escolha **Build Pack: Docker Compose** (arquivo `docker-compose.yml`).
2. Em **Environment Variables**, preencha:
   - `APP_KEY`: gere com `php artisan key:generate --show` (copie o `base64:...` inteiro);
   - `MASTER_PASSWORD`: senha inicial dos dois masters;
   - as senhas do MySQL (`SERVICE_PASSWORD_MYSQL`, `SERVICE_PASSWORD_MYSQLROOT`) e o usuário (`SERVICE_USER_MYSQL`) o Coolify gera sozinho.
3. Em **Domains** do serviço `app`, coloque o domínio com `https://` (ex.: `https://linkvideo.seudominio.com.br`). Ele vira o `APP_URL`.
4. **Deploy**. A cada deploy o container roda as migrations e gera os caches sozinho.
5. Só na primeira vez, no **Terminal** do serviço `app` no Coolify: `php artisan db:seed --force` (cria os masters). Depois disso pode apagar `MASTER_PASSWORD`.

- **Vídeos e capas** ficam no volume `linkvideo-storage` (`storage/app`); o banco, em `linkvideo-mysql`. Não apague os volumes e inclua os dois no backup.
- O PHP confere login/assinatura e o **nginx entrega o arquivo** (`X-Accel-Redirect`, variável `MEDIA_X_ACCEL_MAPPING`): o vídeo não prende um processo PHP enquanto toca.
- **Cloudflare com proxy (nuvem laranja)** na frente: o IP visto passa a ser o da Cloudflare, e o link público do filme (preso ao IP) pode falhar. Use o DNS sem proxy (nuvem cinza).
- **Trazer os dados da hospedagem antiga**: importe o dump do MySQL no serviço `mysql` e copie `storage/app/private/` (videos, covers) para o volume `linkvideo-storage`. O `APP_KEY` pode ser o mesmo de antes ou novo (só invalida sessões e links assinados já abertos).

## Publicar online (hospedagem compartilhada, FTP)

O sistema abre em `https://www.agenciajohncode.com.br/sistemas/linkvideo`. O projeto fica **fora** do `www`; só a parte pública fica acessível. Nesta hospedagem o nginx entrega direto qualquer arquivo que esteja dentro do `www`, então **nunca envie a pasta do projeto inteira para lá**.

```
/home/a/agenciajohncode/
├── linkvideo/                  <- deploy/dist/ENVIAR-POR-FTP/linkvideo   (sistema + .env + vídeos)
└── www/sistemas/linkvideo/     <- deploy/dist/ENVIAR-POR-FTP/www/sistemas/linkvideo  (só 5 itens)
```

Gere o pacote (MySQL do XAMPP ligado):

```bash
bash scripts/gerar-pacote-deploy.sh
```

Ele cria em `deploy/dist/`: a pasta `ENVIAR-POR-FTP/` (espelho do servidor, já com `.env` e `APP_KEY` nova), os mesmos arquivos em dois zips (sem `.env`) e `linkvideo-instalacao.sql`.

1. **Banco** (só na primeira instalação): crie o banco e o usuário no painel; no phpMyAdmin importe `linkvideo-instalacao.sql` (já traz os dois masters).
2. **`.env`**: preencha `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` em `ENVIAR-POR-FTP/linkvideo/.env` (no Finder, `Cmd+Shift+.` mostra arquivos ocultos).
3. **FTP** (com "mostrar arquivos ocultos" ligado): arraste `ENVIAR-POR-FTP/linkvideo` para `/home/a/agenciajohncode/` e o **conteúdo** de `ENVIAR-POR-FTP/www/sistemas/linkvideo/` para `www/sistemas/linkvideo/`.
4. **Permissões (775)**: `linkvideo/storage/` (com subpastas) e `linkvideo/bootstrap/cache/`.
5. **PHP 8.2+** com `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` e `tokenizer`.
6. **Confira**: o endereço abre o login; `.../sistemas/linkvideo/composer.json` dá **404**; cadastre um vídeo pequeno e assista.

**Espaço em disco**: os vídeos ficam em `/home/a/agenciajohncode/linkvideo/storage/app/private/videos` e contam na cota da hospedagem.
**Erro 413 no envio do vídeo**: o servidor recusa partes de 8 MB; coloque `VIDEO_CHUNK_MB=2` no `.env`.

Nunca envie `database/sql/`, `deploy/` ou o `.env` para dentro do `www`.

## Front-end

Blade + Tailwind CSS 4 + Alpine.js, compilados com Vite (`public/build`). Se alterar CSS/JS: `npm install` e `npm run build`.

- Player em modal: `resources/views/partials/player-modal.blade.php` (store Alpine `player` em `resources/js/app.js`).
- Upload em partes: `resources/js/video-uploader.js` + `app/Support/ChunkedUpload.php`.
- Streaming e capas com login: `app/Http/Controllers/MediaController.php`.

## Testes

```bash
/Applications/XAMPP/xamppfiles/bin/php artisan test
```

Usam SQLite em memória e disco falso; não tocam no banco `linkvideo` nem nos vídeos.

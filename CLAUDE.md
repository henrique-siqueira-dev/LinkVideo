# linkVideo — contexto para o Claude

Repositório de vídeos em Laravel 12 (PHP 8.4, MySQL 8.4, Blade + Tailwind 4 + Alpine, Vite). Masters cadastram vídeos; usuários logados assistem; cada filme tem um link fixo público assinado (`/filme/{slug}`). Detalhes de uso e deploy estão no `README.md`; aqui ficam as decisões e o histórico que não aparecem no código.

## Produção

- Coolify, Build Pack **Docker Compose** (`docker-compose.yml` + `Dockerfile`, imagem `serversideup/php:8.4-fpm-nginx`). Domínio `https://linkvideo.sovira.stream`, porta interna **8080**.
- Variáveis no Coolify: `APP_KEY`, `MASTER_PASSWORD`, `VIDEO_STORAGE=noxem`, `NOXEM_API_KEY` (sk_live, escopos streaming.read/write/delete). Senhas do MySQL: `SERVICE_*` geradas pelo Coolify. Valores reais só no Coolify / `.env` local, nunca no repositório.
- A cada deploy o container roda migrations + seeder (`AUTORUN_LARAVEL_MIGRATION_SEED=true`). O seeder cria os masters de `config/linkvideo.php` (hoje 3) com `MASTER_PASSWORD`, só os que faltam, sem trocar senha de quem já existe.
- O Coolify pode entregar `APP_URL` sem esquema (`SERVICE_FQDN_APP` = só o domínio). `AppServiceProvider` completa com `https://`; as URLs do upload são relativas. Isso corrigiu o "blocked:mixed-content" no upload.
- O domínio está atrás do proxy da Cloudflare (nuvem laranja; ela injeta o beacon `cloudflareinsights`, que é inofensivo). O link fixo do filme é preso ao IP: se der 403 para quem assiste, colocar o registro como DNS only (nuvem cinza).
- Mídia local é entregue pelo nginx via X-Accel-Redirect (`MEDIA_X_ACCEL_MAPPING`, `docker/nginx/linkvideo-media.conf` com `location ^~`: sem o `^~` a regex de .mp4 da imagem base causa loop 500).

## Streaming pela Noxem Cloud (`VIDEO_STORAGE=noxem`)

- API `https://api.noxem.cloud/api`, `Authorization: Bearer` (a chave nunca vai em query string, log ou repo). Envelope `{success, data}`.
- Upload: navegador → `POST /uploads/noxem` (servidor cria vídeo + sessão multipart) → `PUT` das partes **direto no R2** por URL pré-assinada (3 em paralelo, `resources/js/noxem-upload.js`) → `/parts` re-assina (lotes de 100) → `/complete` → `POST /videos`. Os bytes nunca passam pela VPS. Estado do envio em Cache (`noxem-upload:{token}`), preso ao usuário.
- Reprodução: `VideoStream::respond` confere acesso e faz **302** para a URL temporária da CDN (`cdn.noxem.cloud`), cacheada por metade do TTL. O JS recupera se a URL vencer (`media-recover.js`).
- Status (`uploading/processing/ready/failed`) consultado ao abrir as telas, no máximo a cada 15 s por vídeo (`NoxemPlayback::refresh`). Enquanto não fica pronto: selo "Noxem · preparando" e aviso no link fixo.
- Lista de vídeos: coluna "Espaço" mostra o tamanho do vídeo "na Noxem"; topo mostra o uso do contrato (`GET /streaming/contract`, cache de 5 min).
- Excluir/trocar vídeo apaga na Noxem. Vídeos antigos do disco: `php artisan linkvideo:noxem-migrar` e depois `--remover-locais`.
- Limitação conhecida: quem copiar a URL da CDN do DevTools baixa o vídeo enquanto ela vale (a API não faz proxy).
- Ainda não validado com a API real: renovação da URL vencida no meio do filme. Se o PUT das partes der erro de CORS, o domínio precisa ser liberado no storage da Noxem.

## Outros pontos

- `sistema-08e2f64ab6d90165bad472b0/` é uma cópia antiga da hospedagem compartilhada baixada por FTP (com vídeos reais). Fica fora do git e do Docker; não editar.
- Deploy antigo por FTP (hospedagem compartilhada) ainda documentado no README (`scripts/gerar-pacote-deploy.sh`).
- `npm run build` local falha por `@rollup/rollup-darwin-arm64` ausente: `rm -rf node_modules && npm install`. O build do Docker funciona.
- Testes: `php artisan test` (SQLite em memória, `Http::fake` para a Noxem). `tests/Feature/NoxemStreamingTest.php` cobre upload, playback, estados e migração.
- Teste manual sem chave real: Noxem simulada e scripts Playwright em `.claude/testing/` (pasta ignorada pelo git). Ver `.claude/testing/LEIA-ME.md`.

## Regras do dono (valem sempre)

- Git/GitHub só leitura para o Claude: deixar as mudanças no working tree e passar os comandos `git add/commit/push` para o dono rodar.
- Zero segredo em arquivo versionado. Antes de terminar: `gitleaks dir . --redact` (nos arquivos de `git ls-files -co --exclude-standard`) e conferir que nenhum valor do `.env` aparece neles.
- Mudança de tela: prints Playwright antes/depois, desktop e celular (o sistema só tem tema escuro).
- Usuário do GitHub: `henrique-siqueira-dev`.
